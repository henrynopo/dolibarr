-- Add payment_date to llx_sgpayroll_payroll_line
ALTER TABLE llx_sgpayroll_payroll_line ADD COLUMN payment_date DATE DEFAULT NULL AFTER status;
