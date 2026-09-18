-- Add weekly schedule column to employee extended profile
ALTER TABLE llx_sgpayroll_employee ADD COLUMN weekly_schedule VARCHAR(255) DEFAULT 'Mon,Tue,Wed,Thu,Fri' COMMENT 'JSON or comma-separated list of working days';
