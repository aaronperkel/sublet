<!-- sql.php -->
<?php include 'top.php' ?>

<main>
    <h2>Create Table sublets</h2>
    <pre>
    CREATE TABLE sublets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_url VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        address VARCHAR(255) NOT NULL,
        semester VARCHAR(20) NOT NULL,
        lat DECIMAL(10,6) NOT NULL,
        lon DECIMAL(10,6) NOT NULL,
        description TEXT,
        posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    </pre>
</main>