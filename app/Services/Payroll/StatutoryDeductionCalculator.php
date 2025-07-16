<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\Country;
use App\Models\TaxJurisdiction;
use App\Models\StatutoryDeductionTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class StatutoryDeductionCalculator
{
    /**
     * Calculate all statutory deductions for an employee.
     */
    public function calculateForEmployee(
        Employee $employee, 
        float $grossSalary, 
        Carbon $payrollDate = null
    ): array {
        $payrollDate = $payrollDate ?? now();
        
        // Get the employee's country and tax jurisdiction
        $country = $this->getEmployeeCountry($employee);
        if (!$country || !$country->supportsPayroll()) {
            return [
                'total_employee_deductions' => 0,
                'total_employer_contributions' => 0,
                'deductions' => [],
                'errors' => ['Country not supported for payroll processing']
            ];
        }

        $jurisdiction = $country->getCurrentTaxJurisdiction();
        if (!$jurisdiction) {
            return [
                'total_employee_deductions' => 0,
                'total_employer_contributions' => 0,
                'deductions' => [],
                'errors' => ['No active tax jurisdiction found for country']
            ];
        }

        // Get all applicable statutory deductions for this jurisdiction
        $templates = $this->getApplicableTemplates($jurisdiction, $payrollDate);
        
        $results = [
            'total_employee_deductions' => 0,
            'total_employer_contributions' => 0,
            'deductions' => [],
            'errors' => []
        ];

        foreach ($templates as $template) {
            try {
                $calculation = $template->calculateDeduction($grossSalary, $employee->pay_frequency);
                
                $deduction = [
                    'template_uuid' => $template->uuid,
                    'code' => $template->code,
                    'name' => $template->name,
                    'type' => $template->deduction_type,
                    'employee_amount' => $calculation['employee_amount'],
                    'employer_amount' => $calculation['employer_amount'],
                    'calculation_details' => $calculation['calculation_details']
                ];

                $results['deductions'][] = $deduction;
                $results['total_employee_deductions'] += $calculation['employee_amount'];
                $results['total_employer_contributions'] += $calculation['employer_amount'];

            } catch (\Exception $e) {
                Log::error("Error calculating statutory deduction for template {$template->code}", [
                    'employee_uuid' => $employee->uuid,
                    'template_uuid' => $template->uuid,
                    'error' => $e->getMessage()
                ]);
                
                $results['errors'][] = "Failed to calculate {$template->name}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Calculate specific statutory deduction by type for an employee.
     */
    public function calculateByType(
        Employee $employee,
        string $deductionType,
        float $grossSalary,
        Carbon $payrollDate = null
    ): ?array {
        $payrollDate = $payrollDate ?? now();
        
        $country = $this->getEmployeeCountry($employee);
        if (!$country || !$country->supportsPayroll()) {
            return null;
        }

        $jurisdiction = $country->getCurrentTaxJurisdiction();
        if (!$jurisdiction) {
            return null;
        }

        $template = StatutoryDeductionTemplate::forJurisdiction($jurisdiction->uuid)
            ->ofType($deductionType)
            ->active()
            ->effectiveAt($payrollDate)
            ->first();

        if (!$template) {
            return null;
        }

        try {
            $calculation = $template->calculateDeduction($grossSalary, $employee->pay_frequency);
            
            return [
                'template_uuid' => $template->uuid,
                'code' => $template->code,
                'name' => $template->name,
                'type' => $template->deduction_type,
                'employee_amount' => $calculation['employee_amount'],
                'employer_amount' => $calculation['employer_amount'],
                'calculation_details' => $calculation['calculation_details']
            ];

        } catch (\Exception $e) {
            Log::error("Error calculating {$deductionType} for employee", [
                'employee_uuid' => $employee->uuid,
                'template_uuid' => $template->uuid,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get PAYE (income tax) calculation for an employee.
     */
    public function calculatePAYE(Employee $employee, float $grossSalary, Carbon $payrollDate = null): ?array
    {
        return $this->calculateByType($employee, 'income_tax', $grossSalary, $payrollDate);
    }

    /**
     * Get UIF (unemployment insurance) calculation for an employee.
     */
    public function calculateUIF(Employee $employee, float $grossSalary, Carbon $payrollDate = null): ?array
    {
        return $this->calculateByType($employee, 'unemployment_insurance', $grossSalary, $payrollDate);
    }

    /**
     * Get social security contributions for an employee.
     */
    public function calculateSocialSecurity(Employee $employee, float $grossSalary, Carbon $payrollDate = null): ?array
    {
        return $this->calculateByType($employee, 'social_security', $grossSalary, $payrollDate);
    }

    /**
     * Get health insurance deductions for an employee.
     */
    public function calculateHealthInsurance(Employee $employee, float $grossSalary, Carbon $payrollDate = null): ?array
    {
        return $this->calculateByType($employee, 'health_insurance', $grossSalary, $payrollDate);
    }

    /**
     * Calculate year-to-date statutory deductions for an employee.
     */
    public function calculateYearToDate(Employee $employee, int $year = null): array
    {
        $year = $year ?? now()->year;
        
        // This would typically query payroll history records
        // For now, we'll return a structure showing what this would include
        return [
            'year' => $year,
            'employee_uuid' => $employee->uuid,
            'ytd_totals' => [
                'gross_salary' => 0,
                'total_employee_deductions' => 0,
                'total_employer_contributions' => 0,
            ],
            'deduction_breakdown' => [
                'income_tax' => 0,
                'unemployment_insurance' => 0,
                'social_security' => 0,
                'health_insurance' => 0,
                'pension' => 0
            ],
            'periods_processed' => 0,
            'last_calculation_date' => null
        ];
    }

    /**
     * Validate if all required statutory deductions are configured for a country.
     */
    public function validateCountryConfiguration(Country $country): array
    {
        $jurisdiction = $country->getCurrentTaxJurisdiction();
        if (!$jurisdiction) {
            return [
                'valid' => false,
                'errors' => ['No active tax jurisdiction configured']
            ];
        }

        $mandatoryDeductions = $country->getMandatoryDeductions();
        $configuredTemplates = StatutoryDeductionTemplate::forJurisdiction($jurisdiction->uuid)
            ->active()
            ->get()
            ->pluck('code')
            ->toArray();

        $missingDeductions = array_diff($mandatoryDeductions, $configuredTemplates);

        return [
            'valid' => empty($missingDeductions),
            'configured_deductions' => $configuredTemplates,
            'missing_deductions' => $missingDeductions,
            'warnings' => empty($missingDeductions) ? [] : [
                'Missing mandatory deduction templates: ' . implode(', ', $missingDeductions)
            ]
        ];
    }

    /**
     * Get preview of statutory deductions without saving.
     */
    public function previewCalculations(
        string $countryCode,
        float $grossSalary,
        array $employeeData = []
    ): array {
        $country = Country::where('iso_code', $countryCode)->first();
        if (!$country) {
            return [
                'error' => 'Country not found',
                'deductions' => []
            ];
        }

        // Create a temporary employee object for calculation purposes
        $tempEmployee = new Employee($employeeData);
        $tempEmployee->salary = $employeeData['basic_salary'] ?? $grossSalary;
        
        // Mock the country relationship
        $tempEmployee->setRelation('company', (object)['country_uuid' => $country->uuid]);

        return $this->calculateForEmployee($tempEmployee, $grossSalary);
    }

    /**
     * Get the country for an employee.
     */
    private function getEmployeeCountry(Employee $employee): ?Country
    {
        // Get country through company relationship
        if ($employee->company && $employee->company->country_uuid) {
            return Country::find($employee->company->country_uuid);
        }

        return null;
    }

    /**
     * Get all applicable statutory deduction templates for a jurisdiction.
     */
    private function getApplicableTemplates(TaxJurisdiction $jurisdiction, Carbon $date): Collection
    {
        return StatutoryDeductionTemplate::forJurisdiction($jurisdiction->uuid)
            ->active()
            ->mandatory()
            ->effectiveAt($date)
            ->orderBy('deduction_type')
            ->get();
    }
}
