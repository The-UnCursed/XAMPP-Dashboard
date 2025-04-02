<?php
// Simple XAMPP Dashboard with Management Features
$htdocsPath = "C:/xampp/htdocs";
$vhostsPath = 'C:/xampp/apache/conf/extra/httpd-vhosts.conf';
$hostsPath = 'C:/Windows/System32/drivers/etc/hosts';
$message = '';
$messageType = '';

function listDirectories($path) {
    $directories = array_filter(glob($path . '/*'), 'is_dir');
    return array_map(fn($dir) => basename($dir), $directories);
}

function listVirtualHosts($path) {
    $vhosts = [];
    if (file_exists($path)) {
        $config = file_get_contents($path);
        // Match each VirtualHost block independently
        preg_match_all('/<VirtualHost\s+[^>]+>(.*?)<\/VirtualHost>/is', $config, $blocks);
        foreach ($blocks[1] as $index => $block) {
            preg_match('/ServerName\s+([^\s]+)/i', $block, $nameMatch);
            preg_match('/DocumentRoot\s+"?([^\s"]+)"?/i', $block, $rootMatch);
            if (!empty($nameMatch) && !empty($rootMatch)) {
                // Extract the exact VirtualHost block for deletion purposes
                preg_match_all('/<VirtualHost\s+[^>]+>(.*?)<\/VirtualHost>/is', $config, $fullBlocks);
                $fullBlock = $fullBlocks[0][$index];
                $vhosts[] = [
                    'host' => $nameMatch[1],
                    'root' => trim($rootMatch[1], '"'),
                    'block' => $fullBlock,
                ];
            }
        }
    }
    return $vhosts;
}

function deleteVirtualHost($serverName, $vhostsPath, $hostsPath) {
    // Step 1: Read the current vhosts configuration
    $config = file_get_contents($vhostsPath);

    // Step 2: Find and remove the VirtualHost block for the specified server name
    $vhosts = listVirtualHosts($vhostsPath);
    $found = false;

    foreach ($vhosts as $vhost) {
        if ($vhost['host'] === $serverName) {
            $found = true;
            // Remove the VirtualHost block
            $config = str_replace($vhost['block'], '', $config);
            break;
        }
    }

    if (!$found) {
        return [false, "Virtual host '$serverName' not found."];
    }

    // Step 3: Write the updated configuration back to the file
    if (!file_put_contents($vhostsPath, $config)) {
        return [false, "Failed to update vhosts configuration. Check permissions."];
    }

    // Step 4: Try to remove the host entry from the hosts file
    $hostsResult = "";
    if (file_exists($hostsPath) && is_writable($hostsPath)) {
        $hostsContent = file_get_contents($hostsPath);
        $updatedHosts = preg_replace('/\n127\.0\.0\.1\s+' . preg_quote($serverName) . '/', '', $hostsContent);
        if ($updatedHosts !== $hostsContent) {
            if (file_put_contents($hostsPath, $updatedHosts)) {
                $hostsResult = " Hosts file entry was also removed.";
            } else {
                $hostsResult = " Could not update hosts file due to permission issues.";
            }
        }
    } else {
        $hostsResult = " Note: You may need to manually remove '127.0.0.1 $serverName' from your hosts file.";
    }

    return [true, "Virtual host '$serverName' deleted successfully.$hostsResult Restart Apache for changes to take effect."];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new project
    if (isset($_POST['create_project'])) {
        $projectName = trim($_POST['project_name']);

        if (empty($projectName)) {
            $message = 'Project name cannot be empty.';
            $messageType = 'error';
        } elseif (preg_match('/[^a-zA-Z0-9_-]/', $projectName)) {
            $message = 'Project name can only contain letters, numbers, hyphens, and underscores.';
            $messageType = 'error';
        } else {
            $projectPath = $htdocsPath . '/' . $projectName;

            if (file_exists($projectPath)) {
                $message = "Project '$projectName' already exists.";
                $messageType = 'error';
            } else {
                if (mkdir($projectPath, 0755)) {
                    // Create an index file
                    $indexContent = "<!DOCTYPE html>\n<html>\n<head>\n\t<title>$projectName</title>\n</head>\n<body>\n\t<h1>Welcome to $projectName</h1>\n\t<p>This is your new project.</p>\n</body>\n</html>";
                    file_put_contents($projectPath . '/index.php', $indexContent);

                    $message = "Project '$projectName' created successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to create project '$projectName'. Check permissions.";
                    $messageType = 'error';
                }
            }
        }
    }

    // Create new virtual host
    if (isset($_POST['create_vhost'])) {
        $serverName = trim($_POST['server_name']);
        $documentRoot = trim($_POST['document_root']);

        if (empty($serverName) || empty($documentRoot)) {
            $message = 'Both server name and document root are required.';
            $messageType = 'error';
        } elseif (!preg_match('/^[a-zA-Z0-9.-]+$/', $serverName)) {
            $message = 'Server name contains invalid characters.';
            $messageType = 'error';
        } else {
            // Check if document root exists
            if (!file_exists($documentRoot)) {
                $message = "Document root '$documentRoot' does not exist.";
                $messageType = 'error';
            } else {
                // Check if virtual host already exists
                $vhosts = listVirtualHosts($vhostsPath);
                $exists = false;

                foreach ($vhosts as $vhost) {
                    if ($vhost['host'] === $serverName) {
                        $exists = true;
                        break;
                    }
                }

                if ($exists) {
                    $message = "Virtual host '$serverName' already exists.";
                    $messageType = 'error';
                } else {
                    // Add to vhosts file
                    $vhostConfig = "\n<VirtualHost *:80>\n\tServerName " . $serverName . "\n\tDocumentRoot \"" . $documentRoot . "\"\n\t<Directory \"" . $documentRoot . "\">\n\t\tOptions Indexes FollowSymLinks\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>\n</VirtualHost>\n";

                    if (file_put_contents($vhostsPath, $vhostConfig, FILE_APPEND)) {
                        // Attempt to add to hosts file (will require admin privileges)
                        $hostsEntry = "\n127.0.0.1\t" . $serverName;
                        $hostsNote = "";

                        // Check if we can write to the hosts file
                        if (is_writable($hostsPath)) {
                            file_put_contents($hostsPath, $hostsEntry, FILE_APPEND);
                        } else {
                            $hostsNote = " Note: You need to manually add '127.0.0.1 $serverName' to your hosts file.";
                        }

                        $message = "Virtual host '$serverName' created successfully. Restart Apache for changes to take effect." . $hostsNote;
                        $messageType = 'success';
                    } else {
                        $message = "Failed to update vhosts configuration. Check permissions.";
                        $messageType = 'error';
                    }
                }
            }
        }
    }

    // Delete virtual host
    if (isset($_POST['delete_vhost'])) {
        $serverName = trim($_POST['server_name']);

        if (empty($serverName)) {
            $message = 'Server name cannot be empty.';
            $messageType = 'error';
        } else {
            list($success, $deleteMessage) = deleteVirtualHost($serverName, $vhostsPath, $hostsPath);
            $message = $deleteMessage;
            $messageType = $success ? 'success' : 'error';
        }
    }
}

$folders = listDirectories($htdocsPath);
$vhosts = listVirtualHosts($vhostsPath);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XAMPP Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
    <div class="container">
        <header>
            <h1>XAMPP Dashboard</h1>
            <div class="quick-links">
                <a href="http://localhost/" class="quick-link">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="http://localhost/phpmyadmin/" class="quick-link">
                    <i class="fas fa-database"></i> phpMyAdmin
                </a>
                <!-- Add your own links -->
                <a href="https://github.com/The-UnCursed" class="quick-link">
                    <i class="fa-brands fa-github"></i> UnCursed
                </a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard">
            <!-- Projects Card -->
            <div class="card">
                <div class="card-header">
                    <h2>Projects</h2>
                    <div class="card-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tabs">
                        <div class="tab active" data-target="projects-list">Projects List</div>
                        <div class="tab" data-target="create-project">Create Project</div>
                    </div>

                    <div id="projects-list" class="tab-content active">
                        <?php if (empty($folders)): ?>
                            <div class="empty-message">No projects found in htdocs</div>
                        <?php else: ?>
                            <ul class="item-list">
                                <?php foreach ($folders as $folder): ?>
                                    <li class="item">
                                        <div class="item-icon">
                                            <i class="fas fa-folder-open"></i>
                                        </div>
                                        <div class="item-content">
                                            <a href="http://localhost/<?php echo $folder; ?>" class="item-title" target="_blank"><?php echo $folder; ?></a>
                                            <div class="item-description">http://localhost/<?php echo $folder; ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div id="create-project" class="tab-content">
                        <form method="post">
                            <div class="form-group">
                                <label for="project_name">Project Name</label>
                                <input type="text" id="project_name" name="project_name" placeholder="e.g., my-awesome-project" required>
                                <div class="helper-text">Use only letters, numbers, hyphens, and underscores.</div>
                            </div>

                            <button type="submit" name="create_project" class="btn-block">
                                <i class="fas fa-plus"></i> Create Project
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Virtual Hosts Card -->
            <div class="card">
                <div class="card-header">
                    <h2>Virtual Hosts</h2>
                    <div class="card-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tabs">
                        <div class="tab active" data-target="vhosts-list">Virtual Hosts List</div>
                        <div class="tab" data-target="create-vhost">Create Virtual Host</div>
                    </div>

                    <div id="vhosts-list" class="tab-content active">
                        <?php if (empty($vhosts)): ?>
                            <div class="empty-message">No virtual hosts configured</div>
                        <?php else: ?>
                            <ul class="item-list">
                                <?php foreach ($vhosts as $vhost): ?>
                                    <li class="item">
                                        <div class="item-icon">
                                            <i class="fas fa-server"></i>
                                        </div>
                                        <div class="item-content">
                                            <a href="http://<?php echo $vhost['host']; ?>" class="item-title" target="_blank"><?php echo $vhost['host']; ?></a>
                                            <div class="item-description"><?php echo $vhost['root']; ?></div>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-delete" onclick="showDeleteConfirmation('<?php echo $vhost['host']; ?>')">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div id="create-vhost" class="tab-content">
                        <form method="post">
                            <div class="form-group">
                                <label for="server_name">Server Name</label>
                                <input type="text" id="server_name" name="server_name" placeholder="e.g., mysite.local" required>
                                <div class="helper-text">Domain name for the virtual host</div>
                            </div>

                            <div class="form-group">
                                <label for="document_root">Document Root</label>
                                <div class="browse-container">
                                    <input type="text" id="document_root" name="document_root" placeholder="e.g., C:/xampp/htdocs/my-project" required>
                                    <button type="button" id="browse-button">
                                        <i class="fas fa-folder-open"></i>
                                    </button>
                                </div>
                                <div class="helper-text">The folder that contains your website files</div>
                            </div>

                            <button type="submit" name="create_vhost" class="btn-block">
                                <i class="fas fa-plus"></i> Create Virtual Host
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <p>XAMPP Dashboard &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <!-- Folder Browser Modal -->
    <div id="folder-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Select Document Root</h3>
                <span class="close-modal">&times;</span>
            </div>

            <div class="folder-tree">
                <div class="folder-item selected" data-path="<?php echo $htdocsPath; ?>">
                    <i class="fas fa-folder"></i> htdocs
                </div>
                <?php foreach ($folders as $folder): ?>
                    <div class="folder-item" data-path="<?php echo $htdocsPath . '/' . $folder; ?>">
                        <i class="fas fa-folder"></i> <?php echo $folder; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" id="select-folder" class="btn-block">
                <i class="fas fa-check"></i> Select Folder
            </button>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal">
        <div class="delete-modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Delete Virtual Host</h3>
            <p class="delete-warning">Are you sure you want to delete the virtual host <strong id="delete-host-name"></strong>?</p>
            <p>This will remove the virtual host configuration and may require a restart of Apache for changes to take effect.</p>

            <form method="post" id="delete-vhost-form">
                <input type="hidden" name="server_name" id="delete-server-name">

                <div class="delete-actions">
                    <button type="button" id="cancel-delete" class="btn-cancel">Cancel</button>
                    <button type="submit" name="delete_vhost" class="btn-confirm-delete">Delete Virtual Host</button>
                </div>
            </form>
        </div>
    </div>

    <a href="#" class="back-to-top"><i class="fas fa-arrow-up"></i></a>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Find the parent card of this tab
                const parentCard = this.closest('.card');

                // Remove active class from all tabs in this card only
                parentCard.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Get the target content ID
                const targetId = this.getAttribute('data-target');

                // Hide all tab contents in this card only
                parentCard.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

                // Show the target tab content in this card
                parentCard.querySelector(`#${targetId}`).classList.add('active');
            });
        });

        // Folder browser modal
        const modal = document.getElementById('folder-modal');
        const browseButton = document.getElementById('browse-button');
        const closeModal = document.querySelector('.close-modal');
        const folderItems = document.querySelectorAll('.folder-item');
        const selectFolderButton = document.getElementById('select-folder');
        const documentRootInput = document.getElementById('document_root');

        browseButton.addEventListener('click', function() {
            modal.style.display = 'block';
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        folderItems.forEach(item => {
            item.addEventListener('click', function() {
                folderItems.forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        selectFolderButton.addEventListener('click', function() {
            const selectedFolder = document.querySelector('.folder-item.selected');
            if (selectedFolder) {
                documentRootInput.value = selectedFolder.getAttribute('data-path');
                modal.style.display = 'none';
            }
        });

        // Delete virtual host confirmation
        function showDeleteConfirmation(hostName) {
            const deleteModal = document.getElementById('delete-modal');
            const deleteHostName = document.getElementById('delete-host-name');
            const deleteServerName = document.getElementById('delete-server-name');

            deleteHostName.textContent = hostName;
            deleteServerName.value = hostName;
            deleteModal.style.display = 'block';
        }

        // Cancel delete button
        document.getElementById('cancel-delete').addEventListener('click', function() {
            document.getElementById('delete-modal').style.display = 'none';
        });

        // Close delete modal when clicking outside
        window.addEventListener('click', function(event) {
            const deleteModal = document.getElementById('delete-modal');
            if (event.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });

        // Back to top functionality
        document.querySelector('.back-to-top').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Show back to top button when scrolling down
        window.addEventListener('scroll', function() {
            var backToTop = document.querySelector('.back-to-top');
            if (window.scrollY > 300) {
                backToTop.style.opacity = '0.8';
            } else {
                backToTop.style.opacity = '0';
            }
        });

        // Auto-hide alert messages after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        if (alerts.length > 0) {
            setTimeout(() => {
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 5000);
        }
    </script>
</body>

</html>