<?php
// Configurar tiempo de sesión extendida (10 horas = 36000 segundos)
$sessionTimeout = 36000;

// Configurar parámetros de sesión ANTES de iniciar la sesión
ini_set('session.gc_maxlifetime', $sessionTimeout);
ini_set('session.cookie_lifetime', $sessionTimeout);

// Solo iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => $sessionTimeout,
        'gc_maxlifetime' => $sessionTimeout,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Verificar inactividad de sesión
function checkSessionTimeout() {
    // Solo verificar si hay sesión activa
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $sessionTimeout = 36000; // 10 horas
    
    // Inicializar LAST_ACTIVITY si no existe
    if (!isset($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
        return true;
    }
    
    // Verificar si ha pasado más tiempo del permitido
    if (time() - $_SESSION['LAST_ACTIVITY'] > $sessionTimeout) {
        // Sesión expirada
        session_unset();
        session_destroy();
        
        // Redirigir al login con mensaje de sesión expirada
        if (!headers_sent()) {
            header("Location: ../index.php?error=session_expired");
        }
        exit();
    }
    
    // Actualizar tiempo de última actividad
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // Regenerar ID de sesión periódicamente (cada 30 minutos)
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
    
    return true;
}

// Verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && checkSessionTimeout();
}

// Redirigir al login si no está autenticado
function requireLogin() {
    if (!isLoggedIn()) {
        if (!headers_sent()) {
            header("Location: ../login.php?error=session_expired");
        }
        exit();
    }
}

// Obtener información completa del usuario logueado
function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Obtener  usuario_id logueado
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Obtener ID del usuario logueado
function getCurrentId() {
    return isset($_SESSION['id']) ? $_SESSION['id'] : null;
}

// Obtener usuario (username)
function getCurrentUsername() {
    $user = getCurrentUser();
    return $user ? $user['usuario'] : null;
}

// Obtener extensión
function getCurrentUserExt() {
    $user = getCurrentUser();
    return $user ? $user['ext'] : null;
}

// Obtener tipo de usuario
function getCurrentUserType() {
    $user = getCurrentUser();
    return $user ? $user['tipo'] : null;
}

// Obtener grupo ID
function getCurrentUserGrupoId() {
    $user = getCurrentUser();
    return $user ? $user['grupo_id'] : null;
}

// Obtener grupo
function getCurrentUserGrupo() {
    $user = getCurrentUser();
    return $user ? $user['grupo'] : null;
}

// Verificar si el usuario es administrador (basado en el tipo)
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['tipo'] == 1; 
}

function isTL() {
    $user = getCurrentUser();
    return $user && ($user['tipo'] == 4 || $user['tipo'] == 5);
}