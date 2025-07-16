<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\EmployeePayrollItem;
use App\Models\CompanyPayrollTemplate;
use App\Models\EmployeeGarnishment;
use App\Services\Payroll\StatutoryDeductionCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollCalculationService
{
    private StatutoryDeductionCalculator $statutoryCalculator;

    public function __construct(StatutoryDeductionCalculator $statutoryCalculator)
    {
        $this->statutoryCalculator = $statutoryCalculator;
    }

    /**
     * Calculate payroll for a single employee.
     */
    public function calculateEmployeePayroll(
        Employee $employee,
        Carbon $payrollPeriodStart,
        Carbon $payrollPeriodEnd,
        array $options = []
    ): array {
        $grossSalary = $this->calculateGrossSalary($employee, $payrollPeriodStart, $payrollPeriodEnd);
        
        // Get company payroll items (allowances/deductions)
        $companyItems = $this->getCompanyPayrollItems($employee, $payrollPeriodStart);
        
        // Get employee-specific payroll items
        $employeeItems = $this->getEmployeePayrollItems($employee, $payrollPeriodStart);
        
        // Calculate statutory deductions
        $statutoryResult = $this->statutoryCalculator->calculateForEmployee(
            $employee, 
            $grossSalary, 
            $payrollPeriodStart
        );

        // Calculate disposable income for garnishments
        $totalAllowances = $companyItems['allowances']->sum('amount') + 
                          $employeeItems['allowances']->sum('amount');
        
        $totalVoluntaryDeductions = $companyItems['deductions']->sum('amount') + 
                                   $employeeItems['deductions']->sum('amount');
        
        $disposableIncome = $grossSalary + $totalAllowances - $statutoryResult['total_employee_deductions'] - $totalVoluntaryDeductions;

        // Calculate garnishments based on disposable income
        $garnishmentResult = $this->calculateGarnishments($employee, $disposableIncome, $payrollPeriodStart);

        // Calculate final totals
        $totalDeductions = $totalVoluntaryDeductions + 
                          $statutoryResult['total_employee_deductions'] + 
                          $garnishmentResult['total_garnished'];

        $netSalary = $grossSalary + $totalAllowances - $totalDeductions;

        return [
            'employee_uuid' => $employee->uuid,
            'payroll_period_start' => $payrollPeriodStart,
            'payroll_period_end' => $payrollPeriodEnd,
            'basic_salary' => $employee->salary,
            'gross_salary' => $grossSalary,
            'total_allowances' => $totalAllowances,
            'total_deductions' => $totalDeductions,
            'total_statutory_deductions' => $statutoryResult['total_employee_deductions'],
            'total_garnishments' => $garnishmentResult['total_garnished'],
            'total_employer_contributions' => $statutoryResult['total_employer_contributions'],
            'disposable_income' => $disposableIncome,
            'net_salary' => $netSalary,
            'payroll_items' => [
                'allowances' => [
                    'company' => $companyItems['allowances']->toArray(),
                    'employee' => $employeeItems['allowances']->toArray()
                ],
                'deductions' => [
                    'company' => $companyItems['deductions']->toArray(),
                    'statutory' => $statutoryResult['deductions'],
                    'employee' => $employeeItems['deductions']->toArray(),
                    'garnishments' => $garnishmentResult['garnishments']
                ]
            ],
            'calculation_date' => now(),
            'errors' => $statutoryResult['errors'] ?? []
        ];
    }

    /**
     * Calculate garnishments for an employee
     */
    private function calculateGarnishments(Employee $employee, float $disposableIncome, Carbon $date): array
    {
        try {
            return EmployeeGarnishment::calculateTotalGarnishments(
                $employee->uuid,
                $disposableIncome,
                $date
            );
        } catch (\Exception $e) {
            Log::error('Error calculating garnishments for employee: ' . $employee->uuid, [
                'error' => $e->getMessage(),
                'disposable_income' => $disposableIncome,
                'date' => $date->toDateString()
            ]);
            
            return [
                'total_garnished' => 0,
                'remaining_disposable_income' => $disposableIncome,
                'garnishments' => []
            ];
        }
    }

    /**
     * Calculate payroll for multiple employees (batch processing).
     */
    public function calculateBatchPayroll(
        Collection $employees,
        Carbon $payrollPeriodStart,
        Carbon $payrollPeriodEnd,
        array $options = []
    ): array {
        $results = [];
        $summary = [
            'total_employees' => $employees->count(),
            'successful_calculations' => 0,
            'failed_calculations' => 0,
            'total_gross_salary' => 0,
            'total_net_salary' => 0,
            'total_statutory_deductions' => 0,
            'total_employer_contributions' => 0,
            'errors' => []
        ];

        foreach ($employees as $employee) {
            try {
                $calculation = $this->calculateEmployeePayroll(
                    $employee,
                    $payrollPeriodStart,
                    $payrollPeriodEnd,
                    $options
                );

                $results[] = $calculation;
                $summary['successful_calculations']++;
                $summary['total_gross_salary'] += $calculation['gross_salary'];
                $summary['total_net_salary'] += $calculation['net_salary'];
                $summary['total_statutory_deductions'] += $calculation['total_statutory_deductions'];
                $summary['total_employer_contributions'] += $calculation['total_employer_contributions'];

                if (!empty($calculation['errors'])) {
                    $summary['errors'][] = [
                        'employee_uuid' => $employee->uuid,
                        'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                        'errors' => $calculation['errors']
                    ];
                }

            } catch (\Exception $e) {
                $summary['failed_calculations']++;
                $summary['errors'][] = [
                    'employee_uuid' => $employee->uuid,
                    'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                    'errors' => ['Calculation failed: ' . $e->getMessage()]
                ];

                Log::error('Payroll calculation failed for employee', [
                    'employee_uuid' => $employee->uuid,
                    'error' => $e->getMessage(),
                    'payroll_period' => $payrollPeriodStart->format('Y-m-d')
                ]);
            }
        }

        return [
            'summary' => $summary,
            'calculations' => $results,
            'payroll_period_start' => $payrollPeriodStart,
            'payroll_period_end' => $payrollPeriodEnd,
            'calculated_at' => now()
        ];
    }

    /**
     * Create and save payroll records from calculations.
     */
    public function createPayrollRecords(
        Company $company,
        array $calculations,
        Carbon $payrollPeriodStart,
        Carbon $payrollPeriodEnd,
        string $status = 'draft'
    ): Payroll {
        return DB::transaction(function () use ($company, $calculations, $payrollPeriodStart, $payrollPeriodEnd, $status) {
            // Create main payroll record
            $payroll = Payroll::create([
                'company_uuid' => $company->uuid,
                'payroll_period_start' => $payrollPeriodStart,
                'payroll_period_end' => $payrollPeriodEnd,
                'total_employees' => count($calculations['calculations']),
                'total_gross_salary' => $calculations['summary']['total_gross_salary'],
                'total_net_salary' => $calculations['summary']['total_net_salary'],
                'total_deductions' => $calculations['summary']['total_statutory_deductions'],
                'total_employer_contributions' => $calculations['summary']['total_employer_contributions'],
                'status' => $status,
                'calculated_at' => now(),
                'created_by' => auth()->id()
            ]);

            // Create individual payroll items for each employee
            foreach ($calculations['calculations'] as $calculation) {
                $this->createEmployeePayrollItems($payroll, $calculation);
            }

            return $payroll;
        });
    }

    /**
     * Approve a payroll run.
     */
    public function approvePayroll(Payroll $payroll, string $approvedBy): bool
    {
        if ($payroll->status !== 'draft') {
            return false;
        }

        return $payroll->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $approvedBy
        ]);
    }

    /**
     * Process (finalize) an approved payroll.
     */
    public function processPayroll(Payroll $payroll): bool
    {
        if ($payroll->status !== 'approved') {
            return false;
        }

        return DB::transaction(function () use ($payroll) {
            // Update status to processed
            $payroll->update([
                'status' => 'processed',
                'processed_at' => now()
            ]);

            // Here you would typically:
            // 1. Generate payment files for banks
            // 2. Update employee YTD totals
            // 3. Send notifications
            // 4. Generate reports

            return true;
        });
    }

    /**
     * Calculate gross salary for an employee for the given period.
     */
    private function calculateGrossSalary(Employee $employee, Carbon $start, Carbon $end): float
    {
        // For now, we'll assume monthly salary
        // In a real system, you might need to handle:
        // - Hourly employees
        // - Pro-rated salaries for partial months
        // - Overtime calculations
        
        $daysInMonth = $start->daysInMonth;
        $daysInPeriod = $start->diffInDays($end) + 1;
        
        if ($daysInPeriod >= $daysInMonth) {
            return $employee->salary;
        }
        
        // Pro-rate for partial month
        return ($employee->salary / $daysInMonth) * $daysInPeriod;
    }

    /**
     * Get company payroll templates applicable to employee.
     */
    private function getCompanyPayrollItems(Employee $employee, Carbon $date): array
    {
        $templates = CompanyPayrollTemplate::forCompany($employee->company_uuid)
            ->active()
            ->effectiveForDate($date)
            ->get();

        $allowances = collect();
        $deductions = collect();

        foreach ($templates as $template) {
            $calculation = $template->calculateForEmployee($employee, $date);
            
            $item = [
                'template_uuid' => $template->uuid,
                'code' => $template->code,
                'name' => $template->name,
                'type' => $template->type,
                'amount' => $calculation['amount'],
                'calculation_details' => $calculation
            ];

            if ($template->type === 'allowance') {
                $allowances->push($item);
            } else {
                $deductions->push($item);
            }
        }

        return [
            'allowances' => $allowances,
            'deductions' => $deductions
        ];
    }

    /**
     * Get employee-specific payroll items.
     */
    private function getEmployeePayrollItems(Employee $employee, Carbon $date): array
    {
        $items = EmployeePayrollItem::forEmployee($employee->uuid)
            ->active()
            ->effectiveForDate($date)
            ->get();

        $allowances = collect();
        $deductions = collect();

        foreach ($items as $item) {
            $amount = $item->calculateAmount($employee->salary, $date);
            
            $calculationItem = [
                'item_uuid' => $item->uuid,
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'amount' => $amount,
                'calculation_method' => $item->calculation_method
            ];

            if ($item->type === 'allowance') {
                $allowances->push($calculationItem);
            } else {
                $deductions->push($calculationItem);
            }
        }

        return [
            'allowances' => $allowances,
            'deductions' => $deductions
        ];
    }

    /**
     * Create payroll items for an employee.
     */
    private function createEmployeePayrollItems(Payroll $payroll, array $calculation): void
    {
        // Create items for all allowances and deductions
        $allItems = array_merge(
            $calculation['payroll_items']['allowances']['company'],
            $calculation['payroll_items']['allowances']['employee'],
            $calculation['payroll_items']['deductions']['company'],
            $calculation['payroll_items']['deductions']['employee'],
            $calculation['payroll_items']['deductions']['statutory']
        );

        foreach ($allItems as $item) {
            PayrollItem::create([
                'payroll_uuid' => $payroll->uuid,
                'employee_uuid' => $calculation['employee_uuid'],
                'code' => $item['code'],
                'name' => $item['name'],
                'type' => $item['type'],
                'amount' => $item['amount'],
                'calculation_details' => $item['calculation_details'] ?? null
            ]);
        }
    }
}