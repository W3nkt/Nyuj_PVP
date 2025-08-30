<?php
// Admin Navigation Component
$current_admin_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-2">
                <nav class="nav nav-pills nav-justified">
                    <a class="nav-link <?php echo $current_admin_page === 'index' ? 'active' : ''; ?>" 
                       href="<?php echo url('admin/index.php'); ?>">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                    <a class="nav-link <?php echo $current_admin_page === 'users' ? 'active' : ''; ?>" 
                       href="<?php echo url('admin/users.php'); ?>">
                        <i class="fas fa-users me-1"></i>Users
                    </a>
                    <a class="nav-link <?php echo $current_admin_page === 'competitors' ? 'active' : ''; ?>" 
                       href="<?php echo url('admin/competitors.php'); ?>">
                        <i class="fas fa-user-friends me-1"></i>Competitors
                    </a>
                    <a class="nav-link <?php echo $current_admin_page === 'events' ? 'active' : ''; ?>" 
                       href="<?php echo url('admin/events.php'); ?>">
                        <i class="fas fa-calendar me-1"></i>Events
                    </a>
                    <a class="nav-link <?php echo $current_admin_page === 'create_event' ? 'active' : ''; ?>" 
                       href="<?php echo url('admin/create_event.php'); ?>">
                        <i class="fas fa-plus me-1"></i>Create Event
                    </a>
                    <a class="nav-link <?php echo $current_admin_page === 'settings' ? 'active' : ''; ?>" 
                       href="<?php echo url('admin/settings.php'); ?>">
                        <i class="fas fa-cogs me-1"></i>Settings
                    </a>
                    <a class="nav-link" 
                       href="/Bull_PVP/audit_chain_explorer.php">
                        <i class="fas fa-link me-1"></i>Audit Chain
                    </a>
                </nav>
            </div>
        </div>
    </div>
</div>