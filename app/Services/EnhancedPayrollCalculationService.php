<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\EmployeePayrollItem;
use App\Models\StatutoryDeductionTemplate;
use App\Models\CompanyPayrollTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EnhancedPayrollCalculationService
{
    private StatutoryDeductionCalculator $statutoryCalculator;
    
    public function __construct(StatutoryDeductionCalculator $statutoryCalculator)
    {
        $this->statutoryCalculator = $statutoryCalculator;
    }

    public function calculatePayroll(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        // 1. Calculate base salary (prorated if needed)
        $baseSalary = $this->calculateBaseSalary($employee, $periodStart, $periodEnd);
        
        // 2. Get all applicable payroll items
        $earnings = $this->calculateEarnings($employee, $baseSalary, $periodStart, $periodEnd);
        $allowances = $this->calculateAllowances($employee, $baseSalary, $periodStart, $periodEnd);
        $benefits = $this->calculateBenefits($employee, $baseSalary, $periodStart, $periodEnd);
        
        // 3. Calculate gross salary
        $grossSalary = $baseSalary + $earnings->sum('amount') + $allowances->sum('amount');
        
        // 4. Calculate statutory deductions
        $statutoryDeductions = $this->statutoryCalculator->calculateAllDeductions(
            $employee, 
            $grossSalary,
            $periodStart,
            $periodEnd
        );
        
        // 5. Calculate company deductions
        $companyDeductions = $this->calculateCompanyDeductions($employee, $grossSalary, $periodStart, $periodEnd);
        
        // 6. Calculate employer contributions
        $employerContributions = $this->calculateEmployerContributions($employee, $grossSalary, $periodStart, $periodEnd);
        
        // 7. Calculate net salary
        $totalDeductions = $statutoryDeductions->sum('employee_amount') + $companyDeductions->sum('amount');
        $netSalary = $grossSalary - $totalDeductions;
        
        return [
            'base_salary' => $baseSalary,
            'earnings' => $earnings,
            'allowances' => $allowances,
            'benefits' => $benefits,
            'gross_salary' => $grossSalary,
            'statutory_deductions' => $statutoryDeductions,
            'company_deductions' => $companyDeductions,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'employer_contributions' => $employerContributions,
            'total_cost_to_company' => $grossSalary + $employerContributions->sum('amount') + $benefits->sum('amount')
        ];
    }

    public function processPayroll(Employee $employee, Carbon $periodStart, Carbon $periodEnd): Payroll
    {
        $calculation = $this->calculatePayroll($employee, $periodStart, $periodEnd);
        
        // Create payroll record
        $payroll = Payroll::create([
            'company_uuid' => $employee->company_uuid,
            'employee_uuid' => $employee->uuid,
            'tax_jurisdiction_uuid' => $employee->tax_jurisdiction_uuid,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'base_salary' => $calculation['base_salary'],
            'gross_salary' => $calculation['gross_salary'],
            'total_deductions' => $calculation['total_deductions'],
            'net_salary' => $calculation['net_salary'],
            'currency_uuid' => $employee->currency_uuid,
            'status' => 'draft'
        ]);

        // Create detailed payroll items
        $this->createPayrollItems($payroll, $calculation);
        
        return $payroll;
    }

    private function calculateBaseSalary(Employee $employee, Carbon $periodStart, Carbon $periodEnd): float
    {
        // Calculate based on pay frequency
        $payFrequency = $employee->pay_frequency ?? 'monthly';
        $baseSalary = $employee->salary;
        
        return match($payFrequency) {
            'weekly' => $this->calculateWeeklySalary($baseSalary, $periodStart, $periodEnd),
            'bi_weekly' => $this->calculateBiWeeklySalary($baseSalary, $periodStart, $periodEnd),
            'monthly' => $this->calculateMonthlySalary($baseSalary, $periodStart, $periodEnd),
            'quarterly' => $this->calculateQuarterlySalary($baseSalary, $periodStart, $periodEnd),
            'annually' => $this->calculateAnnualSalary($baseSalary, $periodStart, $periodEnd),
            default => $baseSalary
        };
    }

    private function calculateEarnings(Employee $employee, float $baseSalary, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return $this->calculatePayrollItemsByType($employee, 'earning', $baseSalary, $periodStart, $periodEnd);
    }

    private function calculateAllowances(Employee $employee, float $baseSalary, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return $this->getEmployeePayrollItems($employee, 'allowance', $periodStart, $periodEnd)
            ->map(function($item) use ($baseSalary, $periodStart, $periodEnd) {
                return [
                    'item' => $item,
                    'amount' => $this->calculateProrationIfNeeded($item, $baseSalary, $periodStart, $periodEnd),
                    'is_taxable' => $item->template?->is_taxable ?? true,
                    'is_pensionable' => $item->template?->is_pensionable ?? true
                ];
            });
    }

    private function calculateBenefits(Employee $employee, float $baseSalary, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return $this->getEmployeePayrollItems($employee, 'benefit', $periodStart, $periodEnd)
            ->map(function($item) use ($baseSalary, $periodStart, $periodEnd) {
                return [
                    'item' => $item,
                    'amount' => $this->calculateProrationIfNeeded($item, $baseSalary, $periodStart, $periodEnd),
                    'is_company_cost' => true
                ];
            });
    }

    private function calculateCompanyDeductions(Employee $employee, float $grossSalary, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return $this->getEmployeePayrollItems($employee, 'deduction', $periodStart, $periodEnd)
            ->map(function($item) use ($grossSalary, $periodStart, $periodEnd) {
                return [
                    'item' => $item,
                    'amount' => $this->calculateProrationIfNeeded($item, $grossSalary, $periodStart, $periodEnd),
                    'is_voluntary' => true
                ];
            });
    }

    private function calculateEmployerContributions(Employee $employee, float $grossSalary, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        // Get statutory employer contributions
        $statutoryContributions = $this->statutoryCalculator->calculateEmployerContributions(
            $employee, 
            $grossSalary,
            $periodStart,
            $periodEnd
        );

        // Get company-defined employer contributions
        $companyContributions = $this->getEmployeePayrollItems($employee, 'employer_cost', $periodStart, $periodEnd)
            ->map(function($item) use ($grossSalary, $periodStart, $periodEnd) {
                return [
                    'item' => $item,
                    'amount' => $this->calculateProrationIfNeeded($item, $grossSalary, $periodStart, $periodEnd),
                    'is_statutory' => false
                ];
            });

        return $statutoryContributions->merge($companyContributions);
    }

    private function getEmployeePayrollItems(Employee $employee, string $type, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return EmployeePayrollItem::where('employee_uuid', $employee->uuid)
            ->where('type', $type)
            ->active()
            ->effectiveForDate($periodStart)
            ->with(['template', 'statutoryTemplate'])
            ->get();
    }

    private function calculateProrationIfNeeded(EmployeePayrollItem $item, float $baseSalary, Carbon $periodStart, Carbon $periodEnd): float
    {
        $amount = $item->calculateAmount($baseSalary, $periodStart);
        
        // Check if proration is needed (e.g., if employee started mid-period)
        if ($this->needsProration($item->employee, $periodStart, $periodEnd)) {
            $workingDays = $this->calculateWorkingDays($item->employee, $periodStart, $periodEnd);
            $totalDays = $periodStart->diffInDays($periodEnd) + 1;
            $amount = $amount * ($workingDays / $totalDays);
        }
        
        return round($amount, 2);
    }

    private function needsProration(Employee $employee, Carbon $periodStart, Carbon $periodEnd): bool
    {
        // Check if employee started during this period
        if ($employee->start_date && Carbon::parse($employee->start_date)->between($periodStart, $periodEnd)) {
            return true;
        }
        
        // Check if employee was terminated during this period
        if ($employee->end_date && Carbon::parse($employee->end_date)->between($periodStart, $periodEnd)) {
            return true;
        }
        
        return false;
    }

    private function calculateWorkingDays(Employee $employee, Carbon $periodStart, Carbon $periodEnd): int
    {
        $startDate = max($periodStart, Carbon::parse($employee->start_date ?? $periodStart));
        $endDate = min($periodEnd, Carbon::parse($employee->end_date ?? $periodEnd));
        
        return $startDate->diffInDays($endDate) + 1;
    }

    private function createPayrollItems(Payroll $payroll, array $calculation): void
    {
        // Create earnings items
        foreach ($calculation['earnings'] as $earning) {
            $this->createPayrollItem($payroll, $earning, 'income', 'earning');
        }

        // Create allowance items
        foreach ($calculation['allowances'] as $allowance) {
            $this->createPayrollItem($payroll, $allowance, 'allowance', 'earning');
        }

        // Create benefit items
        foreach ($calculation['benefits'] as $benefit) {
            $this->createPayrollItem($payroll, $benefit, 'benefit', 'employer_cost');
        }

        // Create statutory deduction items
        foreach ($calculation['statutory_deductions'] as $deduction) {
            PayrollItem::create([
                'payroll_uuid' => $payroll->uuid,
                'statutory_template_uuid' => $deduction['template_uuid'] ?? null,
                'code' => $deduction['code'],
                'name' => $deduction['name'],
                'category' => 'tax',
                'type' => 'deduction',
                'calculation_base' => $deduction['calculation_base'],
                'rate_applied' => $deduction['rate_applied'],
                'employee_amount' => $deduction['employee_amount'],
                'employer_amount' => $deduction['employer_amount'],
                'is_taxable' => false,
                'is_statutory' => true,
                'calculation_details' => $deduction['calculation_details']
            ]);
        }

        // Create company deduction items
        foreach ($calculation['company_deductions'] as $deduction) {
            $this->createPayrollItem($payroll, $deduction, 'deduction', 'deduction');
        }

        // Create employer contribution items
        foreach ($calculation['employer_contributions'] as $contribution) {
            PayrollItem::create([
                'payroll_uuid' => $payroll->uuid,
                'employee_payroll_item_uuid' => $contribution['item']?->uuid,
                'statutory_template_uuid' => $contribution['statutory_template_uuid'] ?? null,
                'code' => $contribution['code'],
                'name' => $contribution['name'],
                'category' => 'employer_contribution',
                'type' => 'employer_cost',
                'calculation_base' => $contribution['calculation_base'] ?? 0,
                'employee_amount' => 0,
                'employer_amount' => $contribution['amount'],
                'is_statutory' => $contribution['is_statutory'] ?? false
            ]);
        }
    }

    private function createPayrollItem(Payroll $payroll, array $itemData, string $category, string $type): void
    {
        PayrollItem::create([
            'payroll_uuid' => $payroll->uuid,
            'employee_payroll_item_uuid' => $itemData['item']->uuid,
            'code' => $itemData['item']->code,
            'name' => $itemData['item']->name,
            'category' => $category,
            'type' => $type,
            'calculation_base' => $itemData['calculation_base'] ?? 0,
            'employee_amount' => $itemData['amount'],
            'employer_amount' => $itemData['employer_amount'] ?? 0,
            'is_taxable' => $itemData['is_taxable'] ?? true,
            'is_statutory' => false
        ]);
    }

    // Salary calculation helpers
    private function calculateMonthlySalary(float $annualSalary, Carbon $periodStart, Carbon $periodEnd): float
    {
        return $annualSalary / 12;
    }

    private function calculateWeeklySalary(float $annualSalary, Carbon $periodStart, Carbon $periodEnd): float
    {
        return $annualSalary / 52;
    }

    private function calculateBiWeeklySalary(float $annualSalary, Carbon $periodStart, Carbon $periodEnd): float
    {
        return $annualSalary / 26;
    }

    private function calculateQuarterlySalary(float $annualSalary, Carbon $periodStart, Carbon $periodEnd): float
    {
        return $annualSalary / 4;
    }

    private function calculateAnnualSalary(float $annualSalary, Carbon $periodStart, Carbon $periodEnd): float
    {
        return $annualSalary;
    }
}