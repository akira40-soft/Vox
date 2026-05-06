<?php
/**
 * footer.php - Vox Electoral Platform
 * Common footer include for authenticated pages
 */
?>
    </main>
    <!-- /Main Content Wrapper -->

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> VOX - Sistema Eleitoral Angolano. Desenvolvido por <strong>Isaac Quarenta</strong> em Luanda, Angola 🇦🇴</p>
        </div>
    </footer>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="assets/js/main.js?v=<?= time() ?>"></script>
    <script>
        // Expose CSRF token to JavaScript
        window.csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    </script>
</body>
</html>

