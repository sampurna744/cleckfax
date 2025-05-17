<?php
session_start();
$product_id = isset($_GET["productId"]) ? $_GET["productId"] : 0;
$user_id = isset($_SESSION["userid"]) ? $_SESSION["userid"] : 0;
$searchText = "p";

// Include the database connection
include("connection/connection.php");

// Handle comment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_comment"])) {
    $review_score = isset($_POST["rating"]) ? (int)$_POST["rating"] : 0;
    $feedback = isset($_POST["comment"]) ? trim($_POST["comment"]) : "";
    
    if ($user_id && $review_score > 0 && $feedback) {
        $check_sql = "SELECT COUNT(*) AS review_count FROM REVIEW WHERE PRODUCT_ID = :product_id AND USER_ID = :user_id";
        $check_stmt = oci_parse($conn, $check_sql);
        oci_bind_by_name($check_stmt, ':product_id', $product_id);
        oci_bind_by_name($check_stmt, ':user_id', $user_id);
        oci_execute($check_stmt);
        $row = oci_fetch_assoc($check_stmt);
        $review_count = $row['REVIEW_COUNT'];
        oci_free_statement($check_stmt);

        if ($review_count == 0) {
            $sql = "INSERT INTO REVIEW (PRODUCT_ID, USER_ID, REVIEW_SCORE, FEEDBACK) 
                    VALUES (:product_id, :user_id, :review_score, :feedback)";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':product_id', $product_id);
            oci_bind_by_name($stmt, ':user_id', $user_id);
            oci_bind_by_name($stmt, ':review_score', $review_score);
            oci_bind_by_name($stmt, ':feedback', $feedback);
            if (oci_execute($stmt)) {
                oci_free_statement($stmt);
                header("Location: product_detail.php?productId=" . $product_id);
                exit;
            } else {
                $error = oci_error($stmt);
                echo "Error submitting review: " . htmlspecialchars($error['message']);
                oci_free_statement($stmt);
            }
        } else {
            echo "You have already submitted a review for this product.";
        }
    } else {
        echo "Please provide a rating and comment, and ensure you are logged in.";
    }
}

// Fetch product details with trader name
$sql = "SELECT 
    p.PRODUCT_ID, 
    p.PRODUCT_NAME, 
    p.PRODUCT_DESCRIPTION, 
    p.PRODUCT_PRICE, 
    p.ALLERGY_INFORMATION, 
    p.USER_ID, 
    p.PRODUCT_PICTURE,
    COALESCE(d.DISCOUNT_PERCENT, '') AS DISCOUNT_PERCENT,
    u.FIRST_NAME || ' ' || u.LAST_NAME AS TRADER_NAME
FROM 
    PRODUCT p
LEFT JOIN 
    discount d ON p.PRODUCT_ID = d.PRODUCT_ID
JOIN 
    CLECK_USER u ON p.USER_ID = u.USER_ID
WHERE 
    p.PRODUCT_ID = :product_id
GROUP BY 
    p.PRODUCT_ID, 
    p.PRODUCT_NAME, 
    p.PRODUCT_DESCRIPTION, 
    p.PRODUCT_PRICE, 
    p.ALLERGY_INFORMATION, 
    p.USER_ID, 
    p.PRODUCT_PICTURE, 
    d.DISCOUNT_PERCENT,
    u.FIRST_NAME, 
    u.LAST_NAME";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

$productId = $row['PRODUCT_ID'];
$productName = $row['PRODUCT_NAME'];
$productDescription = $row['PRODUCT_DESCRIPTION'];
$productPrice = $row['PRODUCT_PRICE'];
$allergyInformation = $row['ALLERGY_INFORMATION'];
$userId = $row['USER_ID'];
$productPicture = $row['PRODUCT_PICTURE'];
$discount_percent = $row["DISCOUNT_PERCENT"];
$traderName = $row["TRADER_NAME"];

oci_free_statement($stmt);

// Fetch current user's reviews
$user_reviews = [];
$other_reviews = [];
if ($user_id) {
    $sql = "SELECT 
        r.REVIEW_SCORE, 
        r.FEEDBACK, 
        u.FIRST_NAME || ' ' || u.LAST_NAME AS NAME, 
        u.USER_PROFILE_PICTURE 
    FROM 
        REVIEW r 
    JOIN 
        CLECK_USER u ON r.USER_ID = u.USER_ID 
    WHERE 
        r.PRODUCT_ID = :product_id AND r.USER_ID = :user_id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':product_id', $product_id);
    oci_bind_by_name($stmt, ':user_id', $user_id);
    oci_execute($stmt);
    while ($row = oci_fetch_assoc($stmt)) {
        $user_reviews[] = $row;
    }
    oci_free_statement($stmt);
}

// Fetch other users' reviews
$sql = "SELECT 
    r.REVIEW_SCORE, 
    r.FEEDBACK, 
    u.FIRST_NAME || ' ' || u.LAST_NAME AS NAME, 
    u.USER_PROFILE_PICTURE 
FROM 
    REVIEW r 
JOIN 
    CLECK_USER u ON r.USER_ID = u.USER_ID 
WHERE 
    r.PRODUCT_ID = :product_id AND r.USER_ID != :user_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_bind_by_name($stmt, ':user_id', $user_id);
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
    $other_reviews[] = $row;
}
oci_free_statement($stmt);

// Fetch other products from the same seller
$sql = "SELECT 
    p.PRODUCT_ID, 
    p.PRODUCT_NAME, 
    p.PRODUCT_PRICE, 
    p.PRODUCT_PICTURE, 
    AVG(r.REVIEW_SCORE) AS AVG_REVIEW_SCORE,
    COUNT(r.REVIEW_ID) AS REVIEW_COUNT,
    COALESCE(d.DISCOUNT_PERCENT, '') AS DISCOUNT_PERCENT
FROM 
    product p
LEFT JOIN 
    review r ON p.PRODUCT_ID = r.PRODUCT_ID
LEFT JOIN 
    discount d ON p.PRODUCT_ID = d.PRODUCT_ID
WHERE 
    p.IS_DISABLED = 1 
    AND p.USER_ID = :user_id 
    AND ADMIN_VERIFIED = 1
GROUP BY 
    p.PRODUCT_ID, 
    p.PRODUCT_NAME, 
    p.PRODUCT_PRICE, 
    p.PRODUCT_PICTURE, 
    d.DISCOUNT_PERCENT";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':user_id', $userId);
oci_execute($stmt);

$products = array();
while ($row = oci_fetch_assoc($stmt)) {
    $products[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleckfax Traders - <?php echo htmlspecialchars($productName); ?></title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f0f8ff;
        }
        .star-rating .fa-star, .star-rating .fa-star-o {
            cursor: pointer;
            color: #ffcc00;
        }
        .star-rating .fa-star-o:hover,
        .star-rating .fa-star-o.active {
            color: #ffcc00;
        }
        .comment-form {
            display: none;
        }
        .review-hidden {
            display: none;
        }
        .image-box {
            padding: 10px;
            background: #fff;
            border-radius: 5px;
        }
        .main-image-container {
            max-height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        .thumbnail-container {
            max-height: 80px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        .user-review {
            background-color: #e6f3ff;
            border-left: 4px solid #3273dc;
        }
        .product-info {
            padding: 10px;
            height: 100%;
        }
        .main-image-container .image {
            display: flex;
            justify-content: center;
            align-items: center;
            width: auto;
            height: auto;
            margin: 0 auto;
        }
        .main-image-container .image img {
            max-width: 200px;
            max-height: 150px;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .main-image-container .image img:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .thumbnail-container .image {
            display: flex;
            justify-content: center;
            align-items: center;
            width: auto;
            height: auto;
            margin: 0 auto;
        }
        .thumbnail-container .image img {
            max-width: 60px;
            max-height: 45px;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .thumbnail-container .image img:hover {
            transform: scale(1.2);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .columns .column.is-half {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .box.image-box, .box.product-info {
            flex-grow: 1;
        }
        #new-comment-btn, #view-more-btn {
            background-color: #f5f5f5;
            color: #4a4a4a;
            border: 1px solid #dbdbdb;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        #new-comment-btn:hover, #view-more-btn:hover {
            background-color: #e8e8e8;
            border-color: #b5b5b5;
            color: #363636;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        #new-comment-btn:active, #view-more-btn:active {
            background-color: #dbdbdb;
            border-color: #a0a0a0;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        #view-more-btn {
            float: right;
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
            font-size: 1rem;
            padding: 0.5rem;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        .heart-icon:hover {
            transform: scale(1.2);
        }
        .heart-icon.active {
            color: #ff3860;
        }
    </style>
</head>
<body>
    <nav class="navbar is-light" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item logo-container" href="index.php">
                <img src="logo.png" alt="Cleckfax Traders Logo" class="header-logo">
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

    <section class="section">
        <div class="columns">
            <div class="column is-half">
                <div class="box image-box main-image-container">
                    <figure class="image">
                        <img src="product_image/<?php echo htmlspecialchars($productPicture); ?>" alt="<?php echo htmlspecialchars($productName); ?>" id="main_image">
                    </figure>
                </div>
                <div class="columns is-multiline">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="column is-one-third">
                            <div class="box image-box thumbnail-container">
                                <figure class="image">
                                    <img src="product_image/<?php echo htmlspecialchars($productPicture); ?>" alt="<?php echo htmlspecialchars($productName); ?>" class="thumbnail">
                                </figure>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="column is-half">
                <div class="box product-info">
                    <h1 class="title is-2"><?php echo htmlspecialchars($productName); ?></h1>
                    <p class="subtitle is-5">By <a href="#"><?php echo htmlspecialchars($traderName); ?></a> <span class="icon has-text-info"><i class="fas fa-heart"></i></span></p>
                    <p class="mb-4"><?php echo htmlspecialchars($productDescription); ?></p>
                    <?php
                    $discount_percent = number_format($discount_percent, 2);
                    $original_price = number_format($productPrice, 2);
                    $discount_amount = ($original_price * $discount_percent) / 100;
                    $discount_price = $original_price - $discount_amount;
                    ?>
                    <p class="title is-4">
                        Price: €<?php echo $discount_percent ? $discount_price : $original_price; ?>
                        <?php if ($discount_percent): ?>
                            <span class="subtitle is-6 has-text-grey-light"><s>€<?php echo $original_price; ?></s> -<?php echo $discount_percent; ?>%</span>
                        <?php endif; ?>
                    </p>
                    <div class="field has-addons mb-4">
                        <p class="control">
                            <button class="button quantity-btn" id="decrease_qty">-</button>
                        </p>
                        <p class="control">
                            <input class="input quantity-input" id="quantity_input" type="number" value="1" min="1">
                        </p>
                        <p class="control">
                            <button class="button quantity-btn" id="increase_qty">+</button>
                        </p>
                    </div>
                    <div class="buttons">
                        <button class="button is-primary" id="buy_now">BUY NOW</button>
                        <button class="button is-success add-to-cart" onclick="addToCart(<?php echo $productId; ?>, <?php echo $user_id; ?>, '<?php echo $searchText; ?>')">
                            <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                            <span>Add to Cart</span>
                        </button>
                        <button class="button is-light" onclick="addToWishlist(<?php echo $productId; ?>, <?php echo $user_id; ?>, '<?php echo $searchText; ?>')">
                            <span class="icon"><i class="fas fa-heart"></i></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="box">
            <h2 class="title has-text-centered">Product Details</h2>
            <div class="tabs is-boxed">
                <ul>
                    <li class="is-active" data-target="ingredients"><a>Ingredients</a></li>
                    <li data-target="allergy"><a>Allergy Info</a></li>
                </ul>
            </div>
            <div id="ingredients" class="content">
                <h3 class="title is-4"><?php echo htmlspecialchars($productName); ?></h3>
                <p><?php echo htmlspecialchars($productDescription); ?></p>
            </div>
            <div id="allergy" class="content" style="display: none;">
                <h3 class="title is-4">Allergy Information</h3>
                <p><?php echo htmlspecialchars($allergyInformation); ?></p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="box">
            <h2 class="title has-text-centered">Rating and Reviews</h2>
            <div class="columns is-multiline" id="review-container">
                <?php foreach ($user_reviews as $index => $review): ?>
                    <div class="column is-half review-item user-review" data-index="<?php echo $index; ?>">
                        <div class="box">
                            <article class="media">
                                <div class="media-left">
                                    <figure class="image is-48x48">
                                        <img src="profile_image/<?php echo htmlspecialchars($review['USER_PROFILE_PICTURE']); ?>" alt="<?php echo htmlspecialchars($review['NAME']); ?>">
                                    </figure>
                                </div>
                                <div class="media-content">
                                    <div class="content">
                                        <p>
                                            <strong><?php echo htmlspecialchars($review['NAME']); ?> (Your Review)</strong>
                                            <br>
                                            <span class="icon-text">
                                                <?php
                                                $rating = round($review['REVIEW_SCORE']);
                                                for ($i = 0; $i < 5; $i++) {
                                                    echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                                }
                                                ?>
                                            </span>
                                            <br>
                                            <?php echo htmlspecialchars($review['FEEDBACK']); ?>
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach (array_slice($other_reviews, 0, 2) as $index => $review): ?>
                    <div class="column is-half review-item" data-index="<?php echo $index + count($user_reviews); ?>">
                        <div class="box">
                            <article class="media">
                                <div class="media-left">
                                    <figure class="image is-48x48">
                                        <img src="profile_image/<?php echo htmlspecialchars($review['USER_PROFILE_PICTURE']); ?>" alt="<?php echo htmlspecialchars($review['NAME']); ?>">
                                    </figure>
                                </div>
                                <div class="media-content">
                                    <div class="content">
                                        <p>
                                            <strong><?php echo htmlspecialchars($review['NAME']); ?></strong>
                                            <br>
                                            <span class="icon-text">
                                                <?php
                                                $rating = round($review['REVIEW_SCORE']);
                                                for ($i = 0; $i < 5; $i++) {
                                                    echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                                }
                                                ?>
                                            </span>
                                            <br>
                                            <?php echo htmlspecialchars($review['FEEDBACK']); ?>
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach (array_slice($other_reviews, 2) as $index => $review): ?>
                    <div class="column is-half review-item review-hidden" data-index="<?php echo $index + 2 + count($user_reviews); ?>">
                        <div class="box">
                            <article class="media">
                                <div class="media-left">
                                    <figure class="image is-48x48">
                                        <img src="product_image/<?php echo htmlspecialchars($review['USER_PROFILE_PICTURE']); ?>" alt="<?php echo htmlspecialchars($review['NAME']); ?>">
                                    </figure>
                                </div>
                                <div class="media-content">
                                    <div class="content">
                                        <p>
                                            <strong><?php echo htmlspecialchars($review['NAME']); ?></strong>
                                            <br>
                                            <span class="icon-text">
                                                <?php
                                                $rating = round($review['REVIEW_SCORE']);
                                                for ($i = 0; $i < 5; $i++) {
                                                    echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                                }
                                                ?>
                                            </span>
                                            <br>
                                            <?php echo htmlspecialchars($review['FEEDBACK']); ?>
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="columns">
                <div class="column is-half">
                    <?php if ($user_id && empty($user_reviews)): ?>
                        <a class="button is-light" id="new-comment-btn">New Comment</a>
                        <form class="comment-form" id="comment-form" method="post" action="">
                            <div class="field">
                                <label class="label">Rating</label>
                                <div class="control star-rating">
                                    <span class="icon has-text-warning"><i class="fas fa-star-o" data-value="1"></i></span>
                                    <span class="icon has-text-warning"><i class="fas fa-star-o" data-value="2"></i></span>
                                    <span class="icon has-text-warning"><i class="fas fa-star-o" data-value="3"></i></span>
                                    <span class="icon has-text-warning"><i class="fas fa-star-o" data-value="4"></i></span>
                                    <span class="icon has-text-warning"><i class="fas fa-star-o" data-value="5"></i></span>
                                    <input type="hidden" name="rating" id="rating" value="0">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Comment</label>
                                <div class="control">
                                    <textarea class="textarea" name="comment" placeholder="Your comment..." required></textarea>
                                </div>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <button class="button is-primary" type="submit" name="submit_comment">Submit</button>
                                    <button class="button is-light" type="button" id="cancel-comment">Cancel</button>
                                </div>
                            </div>
                        </form>
                    <?php elseif ($user_id): ?>
                        <p>You have already submitted a review for this product.</p>
                    <?php else: ?>
                        <a class="button is-light" href="login.php">New Comment</a>
                    <?php endif; ?>
                </div>
                <div class="column is-half has-text-right">
                    <a class="button is-light" id="view-more-btn" <?php echo count($other_reviews) <= 2 ? 'style="display: none;"' : ''; ?>>VIEW MORE...</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2 class="title has-text-centered">Other Products from Same Seller</h2>
        <div class="columns is-multiline is-centered">
            <?php foreach ($products as $index => $product): ?>
                <div class="column is-one-fifth">
                    <div class="card" onclick="redirectToProductPage(<?php echo $product['PRODUCT_ID']; ?>)">
                        <div class="card-image">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <figure class="image is-4by3">
                                    <img src="product_image/<?php echo htmlspecialchars($product['PRODUCT_PICTURE']); ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="card-content">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <p class="title is-6"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></p>
                            </a>
                            <?php
                            $original_price = number_format($product['PRODUCT_PRICE'], 2);
                            $discount_percent = number_format($product['DISCOUNT_PERCENT'], 2);
                            $discount_amount = ($original_price * $discount_percent) / 100;
                            $discount_price = $original_price - $discount_amount;
                            ?>
                            <p class="subtitle is-7">
                                €<?php echo $discount_percent ? $discount_price : $original_price; ?>
                                <?php if ($discount_percent): ?>
                                    <span class="has-text-grey-light"><s>€<?php echo $original_price; ?></s></span>
                                <?php endif; ?>
                            </p>
                            <div class="content">
                                <span class="icon-text">
                                    <?php
                                    $rating = round($product['AVG_REVIEW_SCORE']);
                                    for ($i = 0; $i < 5; $i++) {
                                        echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                    }
                                    ?>
                                    <span>(<?php echo $product['REVIEW_COUNT']; ?>)</span>
                                </span>
                            </div>
                            <div class="product-actions">
                                <a href="add_to_cart.php?productid=<?php echo $product['PRODUCT_ID']; ?>&userid=<?php echo $user_id; ?>&searchtext=<?php echo $searchText; ?>" class="button is-primary is-small">
                                    <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                                    <span>Add to Cart</span>
                                </a>
                                <a href="add_to_wishlist.php?product_id=<?php echo $product['PRODUCT_ID']; ?>&user_id=<?php echo $user_id; ?>&searchtext=<?php echo $searchText; ?>" class="heart-icon">
                                    <i class="fas fa-heart"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="column is-full has-text-right">
                <a class="button is-light">VIEW MORE...</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="columns">
                <div class="column is-half">
                    <div class="footer-logo">
                        <a href="index.php">
                            <img src="logo.png" alt="Cleckfax Traders Logo" class="footer-logo-img">
                        </a>
                    </div>
                    <p class="title is-4">Cleckfax Traders</p>
                    <p>Email: <a href="mailto:info@cleckfaxtraders.com">info@cleckfaxtraders.com</a></p>
                    <p>Phone: <a href="tel:+16466755074">646-675-5074</a></p>
                    <p>3961 Smith Street, New York, United States</p>
                    <div class="buttons mt-4">
                        <a href="https://www.facebook.com/cleckfaxtraders" class="button is-small" target="_blank">
                            <span class="icon"><i class="fab fa-facebook-f"></i></span>
                        </a>
                        <a href="https://www.twitter.com/cleckfaxtraders" class="button is-small" target="_blank">
                            <span class="icon"><i class="fab fa-twitter"></i></span>
                        </a>
                        <a href="https://www.instagram.com/cleckfaxtraders" class="button is-small" target="_blank">
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

    <script>
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

            const quantityInput = document.getElementById('quantity_input');
            document.getElementById('increase_qty').addEventListener('click', () => {
                quantityInput.value = parseInt(quantityInput.value) + 1;
            });
            document.getElementById('decrease_qty').addEventListener('click', () => {
                if (parseInt(quantityInput.value) > 1) {
                    quantityInput.value = parseInt(quantityInput.value) - 1;
                }
            });

            const tabs = document.querySelectorAll('.tabs li');
            const contents = document.querySelectorAll('.content');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('is-active'));
                    tab.classList.add('is-active');
                    contents.forEach(content => {
                        content.style.display = content.id === tab.dataset.target ? 'block' : 'none';
                    });
                });
            });

            const stars = document.querySelectorAll('.star-rating i');
            const ratingInput = document.getElementById('rating');
            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.dataset.value);
                    ratingInput.value = value;
                    stars.forEach(s => {
                        s.className = parseInt(s.dataset.value) <= value ? 'fas fa-star' : 'fas fa-star-o';
                    });
                });
            });

            const newCommentBtn = document.getElementById('new-comment-btn');
            const commentForm = document.getElementById('comment-form');
            const cancelCommentBtn = document.getElementById('cancel-comment');
            if (newCommentBtn && commentForm) {
                newCommentBtn.addEventListener('click', () => {
                    commentForm.style.display = commentForm.style.display === 'block' ? 'none' : 'block';
                });
                cancelCommentBtn.addEventListener('click', () => {
                    commentForm.style.display = 'none';
                    ratingInput.value = 0;
                    stars.forEach(star => star.className = 'fas fa-star-o');
                    commentForm.querySelector('textarea').value = '';
                });
            }

            const viewMoreBtn = document.getElementById('view-more-btn');
            if (viewMoreBtn) {
                viewMoreBtn.addEventListener('click', () => {
                    document.querySelectorAll('.review-hidden').forEach(review => {
                        review.classList.remove('review-hidden');
                    });
                    viewMoreBtn.style.display = 'none';
                });
            }

            const mainImage = document.getElementById('main_image');
            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach((thumbnail, index) => {
                thumbnail.addEventListener('click', () => {
                    mainImage.src = thumbnail.src;
                });
                if (index === 0) {
                    mainImage.src = thumbnail.src;
                }
            });

            document.querySelector('footer form').addEventListener('submit', (e) => {
                e.preventDefault();
                alert('Message sent successfully!');
                e.target.reset();
            });

            document.querySelectorAll('.heart-icon').forEach(icon => {
                icon.addEventListener('click', function(e) {
                    e.preventDefault();
                    this.classList.toggle('active');
                });
            });
        });

        function addToCart(productId, userId, searchText) {
            window.location.href = 'add_to_cart.php?productid=' + productId + '&userid=' + userId + '&searchtext=' + searchText;
        }

        function addToWishlist(productId, userId, searchText) {
            window.location.href = 'add_to_wishlist.php?produt_id=' + productId + '&user_id=' + userId + '&searchtext=' + searchText;
        }

        function redirectToProductPage(productId) {
            window.location.href = "product_detail.php?productId=" + productId;
        }
    </script>
</body>
</html>