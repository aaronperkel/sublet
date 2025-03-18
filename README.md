# UVM Sublets

UVM Sublets is a web application built exclusively for UVM students to post and find sublet listings near campus. It lets users create, view, and manage sublet posts with features like filtering by price, semester, and distance from campus. Students can view listings in both grid and map views, and the application includes administrative features for managing posts.

## Features

- **Create & Manage Posts:** Users can add new sublet posts with images, price, address, semester, and a description.
- **Filtering Options:** Easily filter listings by price range, semester, and distance from campus.
- **Dual Views:** View listings in a grid layout or on an interactive map.
- **User-Specific Actions:** Edit or delete posts (with restrictions, e.g., admin-only deletion).

## Installation

1. Clone the Repository:
   ```bash
   git clone https://github.com/aaronperkel/sublet.git
   cd sublet
   ```

2.	Install Dependencies:
Use Composer to install the required PHP packages.
    ```bash
    composer install
    ```

3.	Setup Environment Variables:
Create a `.env` file in the root directory with the following keys:
    ```bash
    DBNAMEUTIL=your_database_name
    DBUSER=your_database_user
    DBPASS=your_database_password
    GOOGLE_API=your_google_api_key
    ```

4.	Configure Your Web Server:
Set up your Apache or Nginx server to serve the project. Make sure PHP and MySQL are installed and configured.

## Usage
- Post Listings: Log in (via your university credentials) and navigate to the “New Post” page to create a sublet listing.
- Filter Listings: Use the filtering options on the home page or map page to narrow down sublets by price, semester, or distance.
- Edit or Delete: If you have an existing post, you can edit it via the “My Post” page. (Note: Only the admin user can delete posts directly.)

## Repository Structure
```bash
src/
  ├─ css/          # Stylesheets for layout, components, forms, grid, and responsive design
  ├─ js/           # JavaScript files for map initialization, UI interactions, etc.
  ├─ connect-db.php
  ├─ delete_post.php
  ├─ edit_post.php
  ├─ footer.php
  ├─ index.php
  ├─ map.php
  ├─ nav.php
  ├─ new_post.php
  ├─ sql.php
  ├─ top.php       # Contains common header elements, environment setup, and navigation
.gitignore
composer.json
LICENSE
README.md
```

## License
This project is licensed under the [MIT License](LICENSE).

## Contributing
Feel free to fork the repository and submit pull requests. Please ensure any changes adhere to the coding style and include appropriate tests and documentation.

*Created by [Aaron Perkel](http://aaronperkel.com)*