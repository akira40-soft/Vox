<?php
/**
 * config/constants.php - Vox Electoral Platform
 * Global constants and configuration
 */

// ============================================
// APPLICATION SETTINGS
// ============================================
define('APP_NAME', 'Vox');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Africa/Luanda');
define('APP_LOCALE', 'pt_AO');

// Set timezone for all date operations
date_default_timezone_set(APP_TIMEZONE);

// ============================================
// USER ROLES
// ============================================
define('ROLE_ADMIN', 'admin');
define('ROLE_ORGANIZADOR', 'organizador');
define('ROLE_CANDIDATO', 'candidato');
define('ROLE_ELEITOR', 'eleitor');

// All valid roles
define('VALID_ROLES', [ROLE_ADMIN, ROLE_ORGANIZADOR, ROLE_CANDIDATO, ROLE_ELEITOR]);

// ============================================
// USER STATES
// ============================================
define('STATE_ATIVO', 'ativo');
define('STATE_PENDENTE', 'pendente');
define('STATE_BANIDO', 'banido');

define('VALID_STATES', [STATE_ATIVO, STATE_PENDENTE, STATE_BANIDO]);

// ============================================
// VOTING SYSTEM
// ============================================
define('VOTE_TYPE_SIM_NAO', 'sim_nao');
define('VOTE_TYPE_UNICO', 'unico');
define('VOTE_TYPE_MULTIPLO', 'multiplo');
define('VOTE_TYPE_RANKING', 'ranking');

define('VALID_VOTE_TYPES', [
    VOTE_TYPE_SIM_NAO,
    VOTE_TYPE_UNICO,
    VOTE_TYPE_MULTIPLO,
    VOTE_TYPE_RANKING
]);

// ============================================
// ROOM STATES
// ============================================
define('ROOM_STATE_RASCUNHO', 'rascunho');
define('ROOM_STATE_ATIVA', 'ativa');
define('ROOM_STATE_PAUSADA', 'pausada');
define('ROOM_STATE_FINALIZADA', 'finalizada');
define('ROOM_STATE_CANCELADA', 'cancelada');

define('VALID_ROOM_STATES', [
    ROOM_STATE_RASCUNHO,
    ROOM_STATE_ATIVA,
    ROOM_STATE_PAUSADA,
    ROOM_STATE_FINALIZADA,
    ROOM_STATE_CANCELADA
]);

// ============================================
// ROOM TYPES
// ============================================
define('ROOM_TYPE_NACIONAL', 'nacional');
define('ROOM_TYPE_MUNICIPAL', 'municipal');
define('ROOM_TYPE_COMUNITARIO', 'comunitario');
define('ROOM_TYPE_PESQUISA', 'pesquisa');
define('ROOM_TYPE_INSTITUCIONAL', 'institucional');

define('VALID_ROOM_TYPES', [
    ROOM_TYPE_NACIONAL,
    ROOM_TYPE_MUNICIPAL,
    ROOM_TYPE_COMUNITARIO,
    ROOM_TYPE_PESQUISA,
    ROOM_TYPE_INSTITUCIONAL
]);

// ============================================
// INVITATION STATES
// ============================================
define('INVITE_STATE_PENDENTE', 'pendente');
define('INVITE_STATE_ACEITE', 'aceite');
define('INVITE_STATE_RECUSADO', 'recusado');

define('VALID_INVITE_STATES', [
    INVITE_STATE_PENDENTE,
    INVITE_STATE_ACEITE,
    INVITE_STATE_RECUSADO
]);

// ============================================
// SECURITY
// ============================================
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);

// Token validity (in seconds)
define('REMEMBER_TOKEN_VALIDITY', 86400 * 30); // 30 days
define('VERIFY_TOKEN_VALIDITY', 86400 * 3);   // 3 days
define('INVITE_TOKEN_VALIDITY', 86400 * 7);   // 7 days

// ============================================
// PAGINATION
// ============================================
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// ============================================
// FILE UPLOADS
// ============================================
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_FILE_TYPES', ['application/pdf', 'text/plain']);

// ============================================
// NOTIFICATION TYPES
// ============================================
define('NOTIF_TYPE_VOTACAO_CRIADA', 'votacao_criada');
define('NOTIF_TYPE_VOTACAO_INICIADA', 'votacao_iniciada');
define('NOTIF_TYPE_VOTACAO_FINALIZADA', 'votacao_finalizada');
define('NOTIF_TYPE_CONVITE_RECEBIDO', 'convite_recebido');
define('NOTIF_TYPE_CAMPANHA_RESPONDIDA', 'campanha_respondida');
define('NOTIF_TYPE_VOTO_CONFIRMADO', 'voto_confirmado');

// ============================================
// REDIRECT PATHS
// ============================================
define('PATH_LOGIN', 'login.php');
define('PATH_DASHBOARD', 'dashboard.php');
define('PATH_HOME', 'index.php');
define('PATH_LOGOUT', 'logout.php');
