<?php
// Include path configuration first
require_once __DIR__ . '/../config/paths.php';
// Include session management
require_once __DIR__ . '/../config/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Nyuj PVP'; ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo asset('logo/Bull_PVP_Trans.png'); ?>" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <?php
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    
    // Get user profile picture for header
    $user_profile_picture = '';
    if (isLoggedIn()) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->connect();
            $user_id = getUserId();
            
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();
            
            if ($user_data && !empty($user_data['profile_picture'])) {
                $user_profile_picture = $user_data['profile_picture'];
            }
        } catch (Exception $e) {
            // Silently handle database errors - fallback to no profile picture
            error_log('Header profile picture query error: ' . $e->getMessage());
        }
    }
    ?>
    
    <!-- Custom CSS -->
    <link href="<?php echo asset('css/style.css'); ?>?v=<?php echo time(); ?>" rel="stylesheet">
    
    <meta name="description" content="Bull PVP Platform - Compete in skill-based battles with real stakes">
    <meta name="keywords" content="battle, bidding, competition, gaming, esports">
    <meta name="author" content="Bull PVP Platform">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo url('index.php'); ?>">
                <img src="<?php echo asset('logo/Bull_PVP_Trans.png'); ?>" alt="Bull PVP Logo" class="navbar-logo me-2">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'index' ? 'active' : ''; ?>" href="<?php echo url('index.php'); ?>">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="<?php echo url('user/dashboard.php'); ?>">
                                <i class="fas fa-dashboard me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'events' ? 'active' : ''; ?>" href="<?php echo url('user/events.php'); ?>">
                                <i class="fas fa-calendar me-1"></i>Events
                            </a>
                        </li>
                        
                        <?php if (getUserRole() === 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>" 
                                   href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cogs me-1"></i>Admin
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item <?php echo $current_page === 'index' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'active' : ''; ?>" 
                                           href="<?php echo url('admin/index.php'); ?>">
                                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item <?php echo $current_page === 'users' ? 'active' : ''; ?>" 
                                           href="<?php echo url('admin/users.php'); ?>">
                                        <i class="fas fa-users me-2"></i>Users
                                    </a></li>
                                    <li><a class="dropdown-item <?php echo $current_page === 'competitors' ? 'active' : ''; ?>" 
                                           href="<?php echo url('admin/competitors.php'); ?>">
                                        <i class="fas fa-vcard me-2"></i>Competitors
                                    </a></li>
                                    <li><a class="dropdown-item <?php echo $current_page === 'events' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'active' : ''; ?>" 
                                           href="<?php echo url('admin/events.php'); ?>">
                                        <i class="fas fa-calendar me-2"></i>Events
                                    </a></li>
                                    <li><a class="dropdown-item <?php echo $current_page === 'create_event' ? 'active' : ''; ?>" 
                                           href="<?php echo url('admin/create_event.php'); ?>">
                                        <i class="fas fa-plus me-2"></i>Create Event
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>" 
                                           href="<?php echo url('admin/settings.php'); ?>">
                                        <i class="fas fa-cogs me-2"></i>Settings
                                    </a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (getUserRole() === 'streamer'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'voting' ? 'active' : ''; ?>" href="<?php echo url('streamer/voting.php'); ?>">
                                    <i class="fas fa-vote-yea me-1"></i>Voting
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <?php if (!empty($user_profile_picture)): ?>
                                    <img src="<?php echo url($user_profile_picture); ?>" 
                                         alt="Profile Picture" 
                                         class="rounded-circle me-2"
                                         style="width: 32px; height: 32px; object-fit: cover; border: 2px solid var(--primary-color);">
                                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-2x me-2 text-primary"></i>
                                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li class="dropdown-header">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($user_profile_picture)): ?>
                                            <img src="<?php echo url($user_profile_picture); ?>" 
                                                 alt="Profile Picture" 
                                                 class="rounded-circle me-3"
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle fa-2x me-3 text-primary"></i>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                                            <small class="text-muted"><?php echo ucfirst(getUserRole()); ?></small>
                                        </div>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo url('user/profile.php'); ?>">
                                    <i class="fas fa-user-edit me-2"></i>My Profile
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo url('user/wallet.php'); ?>">
                                    <i class="fas fa-wallet me-2"></i>My Wallet
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo url('user/transactions.php'); ?>">
                                    <i class="fas fa-history me-2"></i>Transaction History
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo url('auth/logout.php'); ?>">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('auth/login.php'); ?>">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('auth/register.php'); ?>">
                                <i class="fas fa-user-plus me-1"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <main class="main-content">