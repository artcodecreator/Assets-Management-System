<?php
const APP_NAME = 'Glassy AMS';

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'glassy_ams';
const DB_USER = 'root';
const DB_PASS = '';

const USER_ROLES = ['admin', 'manager', 'viewer'];
const ASSET_STATUSES = ['In Use', 'In Repair', 'In Stock', 'Available'];

// Password policy settings
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_REQUIRE_UPPERCASE = true;
const PASSWORD_REQUIRE_LOWERCASE = true;
const PASSWORD_REQUIRE_NUMBER = true;
const PASSWORD_REQUIRE_SPECIAL = true;
const PASSWORD_HISTORY_COUNT = 5; // Number of passwords to keep in history

// Session settings
const SESSION_TIMEOUT = 3600; // 1 hour in seconds
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_DURATION = 900; // 15 minutes in seconds

date_default_timezone_set('UTC');
