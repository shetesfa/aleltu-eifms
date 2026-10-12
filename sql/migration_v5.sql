-- Migration v5: Add api_token to users for Flutter/mobile API auth
ALTER TABLE users ADD COLUMN IF NOT EXISTS api_token VARCHAR(64) NULL UNIQUE AFTER password_hash;
CREATE INDEX IF NOT EXISTS idx_users_api_token ON users(api_token);

SELECT 'Migration v5 complete: api_token column added' AS result;
