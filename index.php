<?php
// login.php
//if (session_status() === PHP_SESSION_NONE) {
//    session_start();
//}

require_once 'includes/session.php';
include 'config/database.php';

$error = '';

// Si ya está logueado, redirigir según su tipo
if (isset($_SESSION['user_id'])) {
    redirectByUserType($_SESSION['user']['tipo']);
    exit();
}

if ($_POST) {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($usuario) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $query = "SELECT id, Nombre, Usuario, Contraseña, Ext, Tipo, grupo_id, Grupo FROM users WHERE Usuario = :usuario";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar contraseña (asumiendo que está hasheada con password_hash)
                if (password_verify($password, $user['Contraseña'])) {
                    // Iniciar sesión con todos los campos necesarios
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'nombre' => $user['Nombre'],
                        'usuario' => $user['Usuario'],
                        'ext' => $user['Ext'],
                        'tipo' => $user['Tipo'],
                        'grupo_id' => $user['grupo_id'],
                        'grupo' => $user['Grupo']
                    ];
                    
                    // ===== NUEVO: ESTABLECER EXPIRACIÓN DE 12 HORAS =====
                    $_SESSION['login_timestamp'] = time();
                    $_SESSION['session_expiry'] = time() + 43200; // 12 horas en segundos
                    
                    // Establecer tiempo de última actividad
                    $_SESSION['LAST_ACTIVITY'] = time();
                    $_SESSION['CREATED'] = time();
                    // ====================================================
                    
                    // Redirigir según el tipo de usuario
                    redirectByUserType($user['Tipo']);
                    exit();
                } else {
                    $error = "Usuario o contraseña incorrectos";
                }
            } else {
                $error = "Usuario no encontrado";
            }
        } catch (PDOException $e) {
            $error = "Error en el sistema: " . $e->getMessage();
        }
    } else {
        $error = "Por favor complete todos los campos";
    }
}

/**
 * Función para redirigir según el tipo de usuario
 */
function redirectByUserType($tipo) {
    switch ($tipo) {
        case 1: // Administrador
            header("Location: admin/index.php");
            break;
        case 2: // FTD
        case 3: // FTD
            header("Location: agente/index.php");
            break;
        case 4: // Team Leader
        case 5: // Team Leader
            header("Location: tl/index.php");
            break;
        case 6: // Auditor
            header("Location: auditor/index.php");
            break;
        default:
            // Tipo no reconocido, redirigir al login con error
            header("Location: index.php?error=tipo_no_valido");
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #fcc;
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .user-type-info {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #888;
            background: #f5f5f5;
            padding: 8px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Bienvenido</h1>
            <p>Ingresa a tu cuenta</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'tipo_no_valido'): ?>
            <div class="error-message">
                Tipo de usuario no válido. Contacte al administrador.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">Usuario:</label>
                <input type="text" id="usuario" name="usuario" required value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>

        <div class="login-footer">
            <p>Sistema CRM © 2024-2026</p>
        </div>
    </div>
</body>
</html>