You are a senior Laravel backend developer working on a multi-tenant HR system called GM58 HR. The system supports multiple companies (tenants), each with its own set of users, departments, positions, and employees.

Your task is to implement the correct data setup flow for the Employee Management Module, enforcing relational integrity and correct sequencing.

The flow must follow this order:


1. Positions are created.
   - Each position belongs to a company via `company_id`.
   - Table: `positions`
   - Fields: id (UUID), name, description, company_id

2. Departments are created.
   - Each department belongs to a company.
   - Table: `departments`
   - Fields: id (UUID), name, description, company_id

3. Employees are created.
   - Each employee record references:
     - A department (`department_id`)
     - A position (`position_id`)
     - Optionally a manager (`manager_id`) who is also in the same company
     - Optionally a `user_id` for login access
   - Table: `employees`
   - Key Fields: id (UUID), first_name, last_name, email, phone, dob, start_date, department_id, position_id, manager_id, company_id,  is_Director,is_Independent_Contractor, is_UIF_Exempt, employment_type, tax_number, bank_details,pay_frequency, national_id, passport_number, emergency_contact_name, emergency_contact_phone

Constraints:
- `department_id`, `position_id`, and `manager_id` must reference valid records within the same company.
- `manager_id` should support recursive self-referencing (used to build the organogram).
- Use UUIDs for all IDs.
- Add database constraints and validation logic to prevent cross-tenant data leakage.

Deliverables:
- Laravel migrations for `positions`, `departments`, and `employees`
- Seeders for creating sample positions, departments, and 5 dummy employees for new trial companies
- EmployeeController methods: `store`, `update`, `index`, `show`
- Validation rules to ensure proper references (manager is from the same company, etc.)
- create requests, resources, services, repositories

Optional:
- Add a Job or Service that sets up default positions/departments when a company is created
- Include a flag on the company to track whether the setup wizard is complete

Goal:
Ensure that no employee can be created unless valid positions and departments exist first. Prepare for frontend organogram generation using the `manager_id` relationships.
