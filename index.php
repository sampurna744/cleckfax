<?php
session_start();
include("connection/connection.php");

try {
    // Update product stock
    $sql = "UPDATE PRODUCT SET STOCK_AVAILABLE = 'no', IS_DISABLED = 0 WHERE PRODUCT_QUANTITY < 1";
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        throw new Exception("Failed to prepare statement: " . $e['message']);
    }
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        throw new Exception("Failed to execute statement: " . $e['message']);
    }
    oci_free_statement($stmt);

    // Fetch categories
    $categoryArray = [];
    $sql = "SELECT CATEGORY_ID, CATEGORY_TYPE, CATEGORY_IMAGE FROM PRODUCT_CATEGORY";
    $result = oci_parse($conn, $sql);
    oci_execute($result);
    while ($row = oci_fetch_assoc($result)) {
        $categoryArray[] = $row;
    }
    oci_free_statement($result);

    // User session
    $user_id = isset($_SESSION["userid"]) ? $_SESSION["userid"] : 0;
    $searchText = "";

    // Fetch reviews for logged-in users
    $reviews = [];
    if ($user_id > 0) {
        $selectReviewSql = "SELECT REVIEW_ID, PRODUCT_ID FROM REVIEW WHERE REVIEW_PROCIDED = 0 AND USER_ID = :customerId";
        $selectReviewStmt = oci_parse($conn, $selectReviewSql);
        oci_bind_by_name($selectReviewStmt, ':customerId', $user_id);
        if (oci_execute($selectReviewStmt)) {
            while ($row = oci_fetch_assoc($selectReviewStmt)) {
                $productId = $row['PRODUCT_ID'];
                $selectProductSql = "SELECT PRODUCT_ID, PRODUCT_NAME, PRODUCT_PICTURE FROM PRODUCT WHERE PRODUCT_ID = :productId AND IS_DISABLED=1 AND ADMIN_VERIFIED=1";
                $selectProductStmt = oci_parse($conn, $selectProductSql);
                oci_bind_by_name($selectProductStmt, ':productId', $productId);
                if (oci_execute($selectProductStmt)) {
                    $productDetails = oci_fetch_assoc($selectProductStmt);
                    $reviews[] = [
                        'REVIEW_ID' => $row['REVIEW_ID'],
                        'PRODUCT_ID' => $productId,
                        'PRODUCT_NAME' => $productDetails['PRODUCT_NAME'],
                        'PRODUCT_PICTURE' => $productDetails['PRODUCT_PICTURE']
                    ];
                }
                oci_free_statement($selectProductStmt);
            }
        }
        oci_free_statement($selectReviewStmt);
    }

    // Handle review submission
    if (isset($_POST["review_submit"])) {
        function sanitizeInput($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            $data = preg_replace("/[^a-zA-Z0-9\-_.,?!()'\"\s]/", "", $data);
            return $data;
        }
        $submittedRating = sanitizeInput($_POST["rating"]);
        $submittedReview = sanitizeInput($_POST["review"]);
        $reviewId = sanitizeInput($_POST["review_id"]);
        $updateReviewSql = "UPDATE REVIEW SET REVIEW_SCORE = :rating, FEEDBACK = :feedback, REVIEW_PROCIDED = 1, REVIEW_DATE = CURRENT_DATE WHERE REVIEW_ID = :reviewId";
        $updateReviewStmt = oci_parse($conn, $updateReviewSql);
        oci_bind_by_name($updateReviewStmt, ':rating', $submittedRating);
        oci_bind_by_name($updateReviewStmt, ':feedback', $submittedReview);
        oci_bind_by_name($updateReviewStmt, ':reviewId', $reviewId);
        if (oci_execute($updateReviewStmt)) {
            header("Location: {$_SERVER['PHP_SELF']}");
            exit();
        }
        oci_free_statement($updateReviewStmt);
    }

    // Fetch products
    $products = [];
    $selectProductsSql = "SELECT PRODUCT_ID, PRODUCT_DESCRIPTION, PRODUCT_NAME, PRODUCT_PICTURE FROM PRODUCT WHERE IS_DISABLED = 1 AND ADMIN_VERIFIED=1";
    $selectProductsStmt = oci_parse($conn, $selectProductsSql);
    if (oci_execute($selectProductsStmt)) {
        while ($row = oci_fetch_assoc($selectProductsStmt)) {
            $products[] = $row;
        }
    }
    oci_free_statement($selectProductsStmt);

    // Fetch products with reviews and discounts
    $products_review = [];
    $sql = "SELECT 
                p.PRODUCT_ID, 
                p.PRODUCT_NAME, 
                p.PRODUCT_PRICE, 
                p.PRODUCT_PICTURE, 
                AVG(r.REVIEW_SCORE) AS AVG_REVIEW_SCORE,
                COUNT(r.REVIEW_SCORE) AS TOTAL_REVIEWS,
                COALESCE(d.DISCOUNT_PERCENT, '') AS DISCOUNT_PERCENT
            FROM 
                product p
            LEFT JOIN 
                review r ON p.PRODUCT_ID = r.PRODUCT_ID
            LEFT JOIN 
                discount d ON p.PRODUCT_ID = d.PRODUCT_ID
            WHERE 
                p.IS_DISABLED = 1 AND ADMIN_VERIFIED = 1
            GROUP BY 
                p.PRODUCT_ID, p.PRODUCT_NAME, p.PRODUCT_PRICE, p.PRODUCT_PICTURE, d.DISCOUNT_PERCENT";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while ($row = oci_fetch_assoc($stmt)) {
        $products_review[] = $row;
    }
    oci_free_statement($stmt);

    // Randomly select up to 8 products
    $selected_indices = array_rand($products_review, min(8, count($products_review)));
    if (!is_array($selected_indices)) {
        $selected_indices = [$selected_indices];
    }

    // Fetch trader information (same as About Us page)
    $trader_shop = [];
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
    oci_execute($stmt);
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
    <title>HudderFoods</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f8ff;
        }
        .slider-hero {
            background: url('1111.jpg') center center no-repeat;
            background-size: cover;
            }
        .circle-img {
            width: 128px;
            height: 128px;
            object-fit: cover;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .circle-img:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        .product-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .heart-icon {
            background: none;
            border: none;
            color: #4a4a4a;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.5rem;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        .heart-icon:hover {
            transform: scale(1.2);
        }
        .heart-icon.active {
            color: #ff3860;
        }
        /* Trader Card Styles from About Us */
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
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .traders-grid {
                flex-direction: column;
                align-items: center;
            }
            .trader-card {
                width: 100%;
                max-width: 400px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar Section -->
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
                <a class="navbar-item nav-link" href="about.php">About Us</a>
                <a class="navbar-item nav-link" href="productlisting.php">Products</a>
            </div>
            <div class="navbar-end">
                <div class="navbar-item">
                    <input class="input" type="text" placeholder="Search products...">
                </div>
                <div class="navbar-item">
                    <a class="button is-light" href="cart.php">
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

    <!-- Hero Section -->
    <section class="hero is-fullheight is-primary slider-hero">
        <div class="hero-body">
            <div class="container has-text-left">
                <h1 class="title is-1 has-text-white">
                    FRESH - SUPPORT YOUR LOCAL TRADERS
                </h1>
                <p class="subtitle has-text-white">
                    Order by Tuesday midnight for pickup Wed-Fri
                </p>
                <a class="button is-light is-large" href="productlisting.php">Shop Now</a>
            </div>
        </div>
    </section>

    <!-- Top Categories Section -->
    <section class="section">
        <h2 class="title has-text-centered">TOP CATEGORIES</h2>
        <div class="columns is-multiline is-centered">
            <?php foreach ($categoryArray as $category): ?>
                <div class="column is-narrow has-text-centered">
                    <figure class="image is-128x128 is-inline-block">
                        <a href="search_page.php?category_id=<?php echo $category['CATEGORY_ID']; ?>&value=<?php echo urlencode(''); ?>">
                            <img class="circle-img" src="category_picture/<?php echo $category['CATEGORY_IMAGE']; ?>" alt="<?php echo $category['CATEGORY_TYPE']; ?>">
                        </a>
                    </figure>
                    <p class="mt-2"><?php echo $category['CATEGORY_TYPE']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Our Bestsellers Section -->
    <section class="section">
        <h2 class="title has-text-centered">OUR BESTSELLERS</h2>
        <div class="columns is-multiline is-centered">
            <?php
            foreach ($selected_indices as $index):
                $product = $products_review[$index];
            ?>
                <div class="column is-one-third">
                    <div class="card">
                        <div class="card-image">
                            <a href="product.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <figure class="image is-4by3">
                                    <img src="product_image/<?php echo $product['PRODUCT_PICTURE']; ?>" alt="<?php echo $product['PRODUCT_NAME']; ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="card-content">
                            <a href="product.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <p class="title is-5"><?php echo $product['PRODUCT_NAME']; ?></p>
                            </a>
                            <p class="subtitle is-6">
                                <?php
                                $original_price = $product['PRODUCT_PRICE'];
                                $discount_percent = $product['DISCOUNT_PERCENT'];
                                $discount_amount = ($original_price * $discount_percent) / 100;
                                $discount_price = $original_price - $discount_amount;
                                ?>
                                €<?php echo number_format($discount_price, 2); ?>
                                <?php if ($discount_percent): ?>
                                    <span class="has-text-grey-light"><s>€<?php echo number_format($original_price, 2); ?></s></span>
                                <?php endif; ?>
                            </p>
                            <div class="product-rating">
                                <span class="stars">
                                    <?php
                                    $rating = round($product['AVG_REVIEW_SCORE']);
                                    for ($i = 0; $i < 5; $i++) {
                                        if ($i < $rating) {
                                            echo '★';
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                </span>
                                <span class="total-reviews">(<?php echo number_format($product['TOTAL_REVIEWS']); ?>)</span>
                            </div>
                            <div class="product-actions">
                                <a href="add_to_cart.php?productid=<?php echo $product['PRODUCT_ID']; ?>&userid=<?php echo $user_id; ?>&searchtext=<?php echo $searchText; ?>" class="button is-primary">Add to Cart</a>
                                <a href="add_to_wishlist.php?product_id=<?php echo $product['PRODUCT_ID']; ?>&user_id=<?php echo $user_id; ?>&searchtext=<?php echo $searchText; ?>" class="heart-icon"><i class="fas fa-heart"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Unlock Local Community Offer -->
    <section class="section has-background-light">
        <div class="columns is-vcentered">
            <div class="column is-half has-text-centered">
                <h2 class="title">UNLOCK LOCAL COMMUNITY EXCLUSIVE OFFER</h2>
                <a class="button is-primary is-large" href="productlisting.php">Shop Now</a>
            </div>
            <div class="column is-half">
                <figure class="image">
                    <img src="banner2.jpg" alt="Community Offer Banner">
                </figure>
            </div>
        </div>
    </section>

    <!-- Build Your Basket Section -->
    <section class="section">
        <h2 class="title has-text-centered">Build Your Basket</h2>
        <div class="columns is-multiline is-centered">
            <?php
            $build_basket_indices = array_rand($products_review, min(5, count($products_review)));
            if (!is_array($build_basket_indices)) {
                $build_basket_indices = [$build_basket_indices];
            }
            foreach ($build_basket_indices as $index):
                $product = $products_review[$index];
            ?>
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-image">
                            <a href="product.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <figure class="image is-4by3">
                                    <img src="product_image/<?php echo $product['PRODUCT_PICTURE']; ?>" alt="<?php echo $product['PRODUCT_NAME']; ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="card-content">
                            <a href="product.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <p class="title is-6"><?php echo $product['PRODUCT_NAME']; ?></p>
                            </a>
                            <p class="subtitle is-7">
                                <?php
                                $original_price = $product['PRODUCT_PRICE'];
                                $discount_percent = $product['DISCOUNT_PERCENT'];
                                $discount_amount = ($original_price * $discount_percent) / 100;
                                $discount_price = $original_price - $discount_amount;
                                ?>
                                €<?php echo number_format($discount_price, 2); ?>
                                <?php if ($discount_percent): ?>
                                    <span class="has-text-grey-light"><s>€<?php echo number_format($original_price, 2); ?></s></span>
                                <?php endif; ?>
                            </p>
                            <div class="product-actions">
                                <a href="add_to_cart.php?productid=<?php echo $product['PRODUCT_ID']; ?>&userid=<?php echo $user_id; ?>&searchtext=<?php echo $searchText; ?>" class="button is-primary is-small">Add to Cart</a>
                                <a href="add_to_wishlist.php?product_id=<?php echo $product['PRODUCT_ID']; ?>&user_id=<?php echo $user_id; ?>&searchtext=<?php echo $searchText; ?>" class="heart-icon"><i class="fas fa-heart"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Meet Our Traders Section -->
    <section class="section our-traders">
        <h2 class="title has-text-centered">Meet Our Traders</h2>
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
    </section>




    <!-- Footer Section -->
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
                        <a href="https://www.instagram.com/hudderfoods" class="button is-small" target="_blank">
                            <span class="icon"><i class="fab fa-instagram"></i></span>
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
                                <input class="input" type="email" id="email" name="email" placeholder="Email" required>
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

    <!-- JavaScript -->
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>
        // Initialize Swiper for Review Submission (if present)
        <?php if ($user_id > 0 && !empty($reviews)): ?>
            var swiper = new Swiper('.swiper-container', {
                slidesPerView: 3,
                spaceBetween: 30,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                breakpoints: {
                    768: {
                        slidesPerView: 2,
                        spaceBetween: 20
                    },
                    480: {
                        slidesPerView: 1,
                        spaceBetween: 10
                    }
                }
            });
        <?php endif; ?>

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

        // Heart Icon Toggle
        document.querySelectorAll('.heart-icon').forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                this.classList.toggle('active');
                const productId = this.getAttribute('data-product');
                console.log(`Toggled favorite status for ${productId}`);
            });
        });

        // Contact Form Submission
        document.querySelector('form').addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Message sent successfully!');
            e.target.reset();
        });

        function redirectToProductPage(productId) {
            window.location.href = "product.php?productId=" + productId;
        }
    </script>
</body>
</html>