# XAMPP Dashboard

A clean, modern dashboard for XAMPP that simplifies project and virtual host management.

![XAMPP Dashboard Screenshot](screenshot.png)

## Features

- **Project Management**
  - View all projects in your htdocs directory
  - Create new projects with a simple form
  - Quick access to all your local development sites

- **Virtual Host Management**
  - List all configured virtual hosts
  - Create new virtual hosts with an intuitive interface
  - Delete virtual hosts with confirmation
  - Browser integration with automatic hosts file configuration

- **User-Friendly Interface**
  - Modern, responsive design
  - Tab-based navigation
  - Interactive file browser for document root selection
  - Quick links to localhost, phpMyAdmin, and other useful tools

## Installation

1. Clone this repository directly to your XAMPP htdocs directory as the default files:
   ```
   cd C:/xampp/htdocs
   git clone https://github.com/yourusername/xampp-dashboard.git .
   ```
   Note: The dot at the end of the command ensures files are cloned directly into the htdocs folder.

2. Now when you navigate to localhost, you'll see the dashboard:
   ```
   http://localhost/
   ```

3. That's it! Start managing your projects and virtual hosts.

## Requirements

- XAMPP with Apache and PHP
- Write permissions for Apache's vhosts configuration file
- Administrator privileges for hosts file modifications (optional, but recommended for full functionality)

## Configuration

By default, the dashboard is configured for a standard XAMPP installation on Windows. If you have a custom setup, you might need to adjust the following paths in the `index.php` file:

```php
$htdocsPath = "C:/xampp/htdocs";
$vhostsPath = 'C:/xampp/apache/conf/extra/httpd-vhosts.conf';
$hostsPath = 'C:/Windows/System32/drivers/etc/hosts';
```

## Usage

### Creating a New Project

1. Navigate to the "Projects" card
2. Click on the "Create Project" tab
3. Enter a project name (letters, numbers, hyphens, and underscores only)
4. Click "Create Project"
5. A new folder will be created in your htdocs directory with a basic index.php file

### Creating a Virtual Host

1. Navigate to the "Virtual Hosts" card
2. Click on the "Create Virtual Host" tab
3. Enter a server name (e.g., myproject.local)
4. Select or enter a document root
5. Click "Create Virtual Host"
6. Restart Apache for changes to take effect

> **Note:** Creating virtual hosts may require administrator privileges to modify the hosts file. If you don't have sufficient permissions, you'll need to manually add the host entry to your system's hosts file.

### Deleting a Virtual Host

1. In the "Virtual Hosts List" tab, find the virtual host you want to delete
2. Click the "Delete" button
3. Confirm the deletion in the modal dialog
4. Restart Apache for changes to take effect

## Contributing

Contributions are welcome! Feel free to fork this repository and submit pull requests.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.

## Acknowledgments

- [Font Awesome](https://fontawesome.com/) for the icons
- [XAMPP](https://www.apachefriends.org/) for the amazing development environment
