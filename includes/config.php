<?php
// Database and app settings. Override with environment variables in production.

const APP_NAME  = 'Campus SkillX';
const APP_TAGLINE = 'Skill Exchange';

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'campus_skill_exchange');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
