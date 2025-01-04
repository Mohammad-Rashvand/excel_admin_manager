<?php
/* Template Name: Custom Login */
get_header(); ?>
<<style>
/* styles.css */

/* تنظیمات عمومی */
.container {
    width: 100%;
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}

.header {
    background-color: #6200ea;
    color: #fff;
    padding: 20px;
    text-align: center;
}

.content {
    padding: 20px;
}

h1,
h2 {
    margin: 0 0 20px 0;
}

.input-group {
    margin-bottom: 20px;
}

.input-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.input-group input,
.input-group select {
    width: 100%;
    padding: 10px;
    box-sizing: border-box;
    border: 1px solid #ccc;
    border-radius: 4px;
}

button {
    background-color: #6200ea;
    color: #fff;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s;
}

button:hover {
    background-color: #3700b3;
}

.login-card {
    max-width: 400px;
    margin: 0 auto;
    padding: 40px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    background-color: #fff;
}

#today-files-table {
    width: 100%;
    border-collapse: collapse;
}

#today-files-table th,
#today-files-table td {
    padding: 10px;
    border: 1px solid #ccc;
    text-align: left;
}
</style>
<div class="container">
    <div class="login-card">
        <h2>ورود</h2>
        <form id="loginform" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
            <div class="input-group">
                <label for="user_login">نام کاربری</label>
                <input id="user_login" type="text" name="log" required>
            </div>
            <div class="input-group">
                <label for="user_pass">رمز عبور</label>
                <input id="user_pass" type="password" name="pwd" required>
            </div>
            <button type="submit" name="wp-submit">ورود</button>
            <input type="hidden" name="redirect_to" value="<?php echo esc_url(site_url('/login-redirect')); ?>" />
        </form>
    </div>
</div>

<?php get_footer(); ?>