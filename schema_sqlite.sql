CREATE TABLE IF NOT EXISTS user (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS student_information (
    RID INTEGER PRIMARY KEY AUTOINCREMENT,
    STUDENT_NAME TEXT,
    STUDENT_ID TEXT,
    ERN_NO TEXT,
    BATCH TEXT,
    DEPARTMENT TEXT,
    AYEAR TEXT,
    PHOTO BLOB,
    MOBILE TEXT,
    PARENT_MOB TEXT,
    CITY TEXT,
    ADDRESS TEXT
);

CREATE TABLE IF NOT EXISTS student_daily (
    INTIME TEXT,
    OUTTIME TEXT,
    STATUS TEXT,
    RID INTEGER,
    EDATE TEXT,
    STUDENT_NAME TEXT,
    ERN_NO TEXT,
    DEPARTMENT TEXT,
    BATCH TEXT,
    AYEAR TEXT,
    STUDENT_ID TEXT
);

CREATE TABLE IF NOT EXISTS staff_information (
    EID INTEGER PRIMARY KEY AUTOINCREMENT,
    NAME TEXT,
    DESIGNATION TEXT,
    DEPARTMENT TEXT,
    MOBILE TEXT,
    PHOTO BLOB
);

CREATE TABLE IF NOT EXISTS staff_daily (
    RID INTEGER PRIMARY KEY AUTOINCREMENT,
    NAME TEXT,
    DESIGNATION TEXT,
    DEPARTMENT TEXT,
    INTIME TEXT,
    OUTTIME TEXT,
    IN_PHOTO BLOB,
    OUT_PHOTO BLOB,
    VERIFICATION_SCORE REAL DEFAULT 0,
    EDATE TEXT
);

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TEXT,
    updated_by TEXT
);

CREATE TABLE IF NOT EXISTS early_out_exceptions (
    ern_no TEXT PRIMARY KEY,
    student_name TEXT,
    reason TEXT,
    created_at TEXT,
    created_by TEXT
);

CREATE INDEX IF NOT EXISTS idx_student_information_ern ON student_information(ERN_NO);
CREATE INDEX IF NOT EXISTS idx_student_daily_ern_edate_status ON student_daily(ERN_NO, EDATE, STATUS);
CREATE INDEX IF NOT EXISTS idx_student_daily_rid_edate ON student_daily(RID, EDATE);
CREATE INDEX IF NOT EXISTS idx_staff_daily_name_edate ON staff_daily(NAME, EDATE);
CREATE INDEX IF NOT EXISTS idx_early_out_exceptions_name ON early_out_exceptions(student_name);

INSERT OR IGNORE INTO app_settings (setting_key, setting_value, updated_at, updated_by)
VALUES ('early_out_rule_enabled', '1', datetime('now'), 'system');

