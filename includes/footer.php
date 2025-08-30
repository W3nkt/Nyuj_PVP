    </main>
    
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><img src="<?php echo asset('logo/Bull_PVP_Trans.png'); ?>" alt="Bull PVP Logo" class="navbar-logo me-2"> Nyuj PVP</h5>
                    <p class="mb-2">Compete in skill-based battles with real stakes. Fair, transparent, and secure.</p>
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Nyuj PVP. All rights reserved.</p>
                </div>
                <div class="col-md-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo url('index.php'); ?>" class="text-light text-decoration-none">Home</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?php echo url('user/events.php'); ?>" class="text-light text-decoration-none">Events</a></li>
                            <li><a href="<?php echo url('user/wallet.php'); ?>" class="text-light text-decoration-none">Wallet</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo url('auth/login.php'); ?>" class="text-light text-decoration-none">Login</a></li>
                            <li><a href="<?php echo url('auth/register.php'); ?>" class="text-light text-decoration-none">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">Help Center</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Terms of Service</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Privacy Policy</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Contact Us</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-white">
                        <i class="fas fa-shield-alt me-1"></i>
                        Secure platform with transparent result verification
                    </small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-white">
                        <i class="fas fa-clock me-1"></i>
                        Last updated: <?php echo date('M j, Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?php echo htmlspecialchars($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>