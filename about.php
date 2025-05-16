<?php
session_start();
include("connection/connection.php");

try {
    // Prepare and execute UPDATE statement for product stock
    $sql = "UPDATE PRODUCT SET STOCK_AVAILABLE = 'no', IS_DISABLED = 0 WHERE PRODUCT_QUANTITY < 1";
    $stmt = oci_parse($conn, $sql);
    
    if (!$stmt) {
        $e = oci_error($conn);
        throw new Exception("Failed to prepare statement: " . $e['message']);
    }

    $r = oci_execute($stmt);
    if (!$r) {
        $e = oci_error($stmt);
        throw new Exception("Failed to execute statement: " . $e['message']);
    }

    oci_free_statement($stmt);

    // Fetch trader data
    $sql = "SELECT 
                u.FIRST_NAME || ' ' || u.LAST_NAME AS NAME, 
                u.USER_PROFILE_PICTURE,
                s.SHOP_DESCRIPTION
            FROM 
                CLECK_USER u 
            JOIN 
                SHOP s ON u.USER_ID = s.USER_ID 
            WHERE 
                u.USER_TYPE = 'trader'";
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        throw new Exception("Failed to prepare trader statement: " . $e['message']);
    }

    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        throw new Exception("Failed to execute trader statement: " . $e['message']);
    }

    $trader_shop = [];
    while ($row = oci_fetch_assoc($stmt)) {
        // Truncate description to 10 words
        $description = $row['SHOP_DESCRIPTION'];
        $words = explode(' ', trim($description));
        $row['SHOP_DESCRIPTION'] = implode(' ', array_slice($words, 0, 10));
        $trader_shop[] = $row;
    }

    oci_free_statement($stmt);
    oci_close($conn);

} catch (Exception $e) {
    error_log($e->getMessage());
    $error_message = "An error occurred while processing data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us - HudderFoods</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }
    body {
      background-color: rgb(234, 238, 239);
      color: #333;
    }
    .container {
      width: 90%;
      max-width: 1200px;
      margin: 0 auto;
    }
    .hero-image img {
      width: 100%;
      height: 300px;
      object-fit: cover;
    }
    /* Fix invalid CSS */
    .navbar-item.nav-link::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: 0;
      left: 0;
      background-color: #48c774;
      transition: width 0.3s ease;
    }
    /* Navigation */
    .navbar {
      background-color: #e5e7eb;
      padding: 0.5rem 1rem;
    }
    .nav-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .nav-left {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .nav-left img.header-logo {
      max-height: 50px;
    }
    .nav-left a, .nav-right a {
      text-decoration: none;
      color: #6b7280;
      font-size: 16px;
    }
    .nav-left a.active {
      font-weight: bold;
      color: #374151;
    }
    .navbar-item.nav-link {
      position: relative;
      transition: color 0.3s ease;
    }
    .navbar-item.nav-link:hover {
      color: #48c774 !important;
    }
    .navbar-item.nav-link:hover::after {
      width: 100%;
    }
    .nav-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .nav-right input {
      padding: 5px;
      border: 1px solid #d1d5db;
      border-radius: 4px;
      width: 150px;
    }
    /* About Us Section */
    .about-us {
      text-align: center;
      padding: 50px 0;
    }
    .about-us h1 {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .about-us p {
      color: #6b7280;
      margin-bottom: 20px;
    }
    .vision-mission {
      display: flex;
      justify-content: center;
      gap: 20px;
    }
    .vision-mission div {
      background-color: #f3f4f6;
      padding: 20px;
      width: 40%;
      text-align: left;
    }
    .vision-mission h3 {
      font-size: 18px;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .vision-mission p {
      color: #6b7280;
      font-size: 14px;
    }
    /* Our Traders Section */
    .our-traders {
      text-align: center;
      padding: 50px 0;
    }
    .our-traders h2 {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .traders-grid {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-top: 30px;
    }
    .trader-card {
      background-color: #f3f4f6;
      text-align: center;
      padding: 20px;
      width: 18%;
    }
    .trader-card .image-placeholder {
      background-color: #e5e7eb;
      height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 10px;
      overflow: hidden;
    }
    .trader-card .image-placeholder img {
      max-width: 100%;
      max-height: 100%;
      object-fit: cover;
    }
    .trader-card h3 {
      font-size: 16px;
      font-weight: bold;
      margin-bottom: 5px;
    }
    .trader-card p {
      color: #6b7280;
      font-size: 14px;
    }
    .trader-card .social-icons {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 10px;
    }
    .trader-card .social-icons a {
      color: #6b7280;
      font-size: 1.2rem;
      transition: color 0.3s ease;
    }
    .trader-card .social-icons a:hover {
      color: #3273dc;
    }
    /* Be a Trader Section */
    .be-trader {
      text-align: center;
      padding: 50px 0;
    }
    .be-trader h2 {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .be-trader a.button {
      background-color: #374151;
      color: #fff;
      border: none;
      padding: 10px 20px;
      cursor: pointer;
      transition: background-color 0.2s;
      text-decoration: none;
    }
    .be-trader a.button:hover {
      background-color: #4b5563;
    }
    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .vision-mission, .traders-grid {
        flex-direction: column;
        align-items: center;
      }
      .vision-mission div, .trader-card {
        width: 100%;
        max-width: 400px;
      }
      .nav-right input {
        width: 120px;
      }
      .navbar-menu {
        text-align: center;
      }
      .footer .columns {
        flex-direction: column;
        text-align: center;
      }
      .footer .social-icons {
        justify-content: center;
        display: flex;
      }
      .footer-logo img {
        margin: 0 auto;
        display: block;
      }
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar is-light" role="navigation" aria-label="main navigation">
    <div class="navbar-brand">
      <a class="navbar-item logo-container" href="index.php">
        <img src="logo.png" alt="HudderFoods Logo" class="header-logo">
      </a>
      <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
      </a>
    </div>
    <div id="navbarMenu" class="navbar-menu">
      <div class="navbar-start">
        <a class="navbar-item nav-link" href="productlisting.php">Shop</a>
        <a class="navbar-item nav-link is-active" href="aboutus.php">About Us</a>
        <a class="navbar-item nav-link" href="productlisting.php">Products</a>
      </div>
      <div class="navbar-end">
        <div class="navbar-item">
          <input class="input" type="text" placeholder="Search products...">
        </div>
        <div class="navbar-item">
          <a class="button is-light" href="#">
            <span class="icon"><i class="fas fa-shopping-cart"></i></span>
            <span>Cart (0)</span>
          </a>
        </div>
        <div class="navbar-item">
          <a class="button is-primary" href="login.php">Login</a>
        </div>
        <div class="navbar-item">
          <a class="button is-success" href="traderregister.php">Become a trader</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- About Us Page -->
  <section class="page about-us-page active" id="about-us-page">
    <div class="container">
      <?php if (isset($error_message)): ?>
        <div class="notification is-danger">
          <?php echo $error_message; ?>
        </div>
      <?php endif; ?>

      <div class="about-us">
        <h1 class="title has-text-centered">About Us</h1>
        <p class="has-text-centered">Occaeat ipsum reprehenderit veniam anim laborum esse duis occaecat representend erat pariatur.</p>
        <div class="hero-image">
          <img src="static/images.jpg" alt="Hero Image">
        </div>
        <div class="vision-mission">
          <div class="box">
            <h3 class="title is-4">Vision</h3>
            <p>Ex nisi in minim ad lorem ad nostril illum. Fugiat veniam adipiscing nulla amet cupidatat sunt dolore nisi ulamco exercitation exercitation Mollit occaecat tempus.</p>
          </div>
          <div class="box">
            <h3 class="title is-4">Mission</h3>
            <p>Ex nisi in minim ad lorem ad nostril illum. Fugiat veniam adipiscing nulla amet cupidatat sunt dolore nisi ulamco exercitation exercitation Mollit occaecat tempus.</p>
          </div>
        </div>
      </div>

      <div class="our-traders">
        <h2 class="title has-text-centered">Our Traders</h2>
        <p class="has-text-centered">Do consectetur proident id eiusmod deserunt consectetur pariatur ad ex velit do Lorem representend.</p>
        <div class="traders-grid">
          <?php foreach ($trader_shop as $shop): ?>
            <div class="trader-card">
              <div class="image-placeholder">
                <img src="profile_image/<?php echo $shop['USER_PROFILE_PICTURE']; ?>" alt="<?php echo $shop['NAME']; ?>">
              </div>
              <h3><?php echo $shop['NAME']; ?></h3>
              <p>Trader</p>
              <p><?php echo $shop['SHOP_DESCRIPTION']; ?></p>
              <div class="social-icons">
                <a href="https://www.facebook.com/<?php echo strtolower(str_replace(' ', '.', $shop['NAME'])); ?>" target="_blank" aria-label="Facebook">
                  <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://www.linkedin.com/in/<?php echo strtolower(str_replace(' ', '-', $shop['NAME'])); ?>" target="_blank" aria-label="LinkedIn">
                  <i class="fab fa-linkedin-in"></i>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="be-trader">
        <h2 class="title has-text-centered">Be a Trader</h2>
        <p class="has-text-centered">Do consectetur proident id eiusmod deserunt consectetur pariatur ad ex velit do Lorem representend.</p>
        <a href="traderregister.php" class="button is-dark">Join us</a>
      </div>
    </div>

    <footer class="footer">
      <div class="container">
        <div class="columns">
          <div class="column is-half">
            <div class="footer-logo">
              <a href="index.php">
                <img src="logo.png" alt="HudderFoods Logo" class="footer-logo-img">
              </a>
            </div>
            <p class="title is-4">HudderFoods</p>
            <p>Email: <a href="mailto:info@hudderfoods.com">info@hudderfoods.com</a></p>
            <p>Phone: <a href="tel:+16466755074">646-675-5074</a></p>
            <p>3961 Smith Street, New York, United States</p>
            <div class="buttons mt-4">
              <a href="https://www.facebook.com/hudderfoods" class="button is-small" target="_blank">
                <span class="icon"><i class="fab fa-facebook-f"></i></span>
              </a>
              <a href="https://www.twitter.com/hudderfoods" class="button is-small" target="_blank">
                <span class="icon"><i class="fab fa-twitter"></i></span>
              </a>
              <a href="https://www.linkedin.com/company/hudderfoods" class="button is-small" target="_blank">
                <span class="icon"><i class="fab fa-linkedin-in"></i></span>
              </a>
            </div>
          </div>
          <div class="column is-half">
            <h2 class="title is-4">Contact Us</h2>
            <form method="post" action="/contact">
              <div class="field">
                <label class="label" for="name">Name</label>
                <div class="control">
                  <input class="input" type="text" id="name" name="name" placeholder="Name" required>
                </div>
              </div>
              <div class="field">
                <label class="label" for="email">Email</label>
                <div class="control">
                  <input class="input" type="text" id="email" name="email" placeholder="Email" required>
                </div>
              </div>
              <div class="field">
                <label class="label" for="message">Message</label>
                <div class="control">
                  <textarea class="textarea" id="message" name="message" placeholder="Type your message here..." required></textarea>
                </div>
              </div>
              <div class="field">
                <div class="control">
                  <button class="button is-primary" type="submit">Send</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </footer>
  </section>

  <script>
    // Navbar Burger Toggle
    document.addEventListener('DOMContentLoaded', () => {
      const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
      if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach(el => {
          el.addEventListener('click', () => {
            const target = el.dataset.target;
            const $target = document.getElementById(target);
            el.classList.toggle('is-active');
            $target.classList.toggle('is-active');
          });
        });
      }
    });

    // Contact Form Submission
    document.querySelector('form').addEventListener('submit', (e) => {
      e.preventDefault();
      alert('Message sent successfully!');
      e.target.reset();
    });
  </script>
</body>
</html>