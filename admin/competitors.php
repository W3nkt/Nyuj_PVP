<?php
$page_title = 'Competitor Management - Bull PVP Admin';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireRole('admin');

$db = new Database();
$conn = $db->connect();

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_competitor':
                $result = handleAddCompetitor();
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;
            case 'update_competitor':
                $result = handleUpdateCompetitor();
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;
            case 'delete_competitor':
                $result = handleDeleteCompetitor();
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;
        }
    }
}

function handleAddCompetitor() {
    global $conn;
    
    try {
        $name = trim($_POST['name']);
        $nickname = trim($_POST['nickname']);
        $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
        $height = trim($_POST['height']);
        $weight = trim($_POST['weight']);
        $nationality = trim($_POST['nationality']);
        $fighting_style = trim($_POST['fighting_style']);
        $experience_years = !empty($_POST['experience_years']) ? intval($_POST['experience_years']) : null;
        $bio = trim($_POST['bio']);
        $achievements = trim($_POST['achievements']);
        
        if (empty($name)) {
            throw new Exception('Competitor name is required');
        }
        
        // Handle image upload
        $profile_image = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleImageUpload($_FILES['profile_image'], 'competitor');
            if ($upload_result['success']) {
                $profile_image = $upload_result['path'];
            } else {
                throw new Exception($upload_result['message']);
            }
        }
        
        // Check if competitor name already exists
        $stmt = $conn->prepare("SELECT id FROM competitor_info WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new Exception('A competitor with this name already exists');
        }
        
        // Insert new competitor
        $stmt = $conn->prepare("
            INSERT INTO competitor_info (name, nickname, age, height, weight, nationality, fighting_style, 
                                       experience_years, profile_image, bio, achievements)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $name, $nickname, $age, $height, $weight, $nationality, 
            $fighting_style, $experience_years, $profile_image, $bio, $achievements
        ]);
        
        return ['success' => true, 'message' => 'Competitor registered successfully!'];
        
    } catch (Exception $e) {
        error_log('Add competitor error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function handleUpdateCompetitor() {
    global $conn;
    
    try {
        $id = intval($_POST['competitor_id']);
        $name = trim($_POST['name']);
        $nickname = trim($_POST['nickname']);
        $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
        $height = trim($_POST['height']);
        $weight = trim($_POST['weight']);
        $nationality = trim($_POST['nationality']);
        $fighting_style = trim($_POST['fighting_style']);
        $experience_years = !empty($_POST['experience_years']) ? intval($_POST['experience_years']) : null;
        $total_fights = !empty($_POST['total_fights']) ? intval($_POST['total_fights']) : 0;
        $wins = !empty($_POST['wins']) ? intval($_POST['wins']) : 0;
        $losses = !empty($_POST['losses']) ? intval($_POST['losses']) : 0;
        $draws = !empty($_POST['draws']) ? intval($_POST['draws']) : 0;
        $bio = trim($_POST['bio']);
        $achievements = trim($_POST['achievements']);
        $status = $_POST['status'];
        
        if (empty($name)) {
            throw new Exception('Competitor name is required');
        }
        
        // Calculate win rate
        $win_rate = $total_fights > 0 ? round(($wins / $total_fights) * 100, 2) : 0;
        
        // Handle image upload
        $profile_image_update = '';
        $image_params = [];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleImageUpload($_FILES['profile_image'], 'competitor');
            if ($upload_result['success']) {
                $profile_image_update = ', profile_image = ?';
                $image_params[] = $upload_result['path'];
            } else {
                throw new Exception($upload_result['message']);
            }
        }
        
        // Update competitor
        $stmt = $conn->prepare("
            UPDATE competitor_info 
            SET name = ?, nickname = ?, age = ?, height = ?, weight = ?, nationality = ?, 
                fighting_style = ?, experience_years = ?, total_fights = ?, wins = ?, 
                losses = ?, draws = ?, win_rate = ?, bio = ?, achievements = ?, status = ?
                {$profile_image_update}
            WHERE id = ?
        ");
        
        $params = [
            $name, $nickname, $age, $height, $weight, $nationality, 
            $fighting_style, $experience_years, $total_fights, $wins, 
            $losses, $draws, $win_rate, $bio, $achievements, $status
        ];
        
        $params = array_merge($params, $image_params);
        $params[] = $id;
        
        $stmt->execute($params);
        
        return ['success' => true, 'message' => 'Competitor updated successfully!'];
        
    } catch (Exception $e) {
        error_log('Update competitor error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function handleDeleteCompetitor() {
    global $conn;
    
    try {
        $id = intval($_POST['competitor_id']);
        
        // Check if competitor is used in any events
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE competitor_a = (SELECT name FROM competitor_info WHERE id = ?) OR competitor_b = (SELECT name FROM competitor_info WHERE id = ?)");
        $stmt->execute([$id, $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception('Cannot delete competitor: they are assigned to existing events');
        }
        
        // Get image path before deletion
        $stmt = $conn->prepare("SELECT profile_image FROM competitor_info WHERE id = ?");
        $stmt->execute([$id]);
        $competitor = $stmt->fetch();
        
        // Delete competitor
        $stmt = $conn->prepare("DELETE FROM competitor_info WHERE id = ?");
        $stmt->execute([$id]);
        
        // Delete image file if exists
        if ($competitor && $competitor['profile_image'] && file_exists('../' . $competitor['profile_image'])) {
            unlink('../' . $competitor['profile_image']);
        }
        
        return ['success' => true, 'message' => 'Competitor deleted successfully!'];
        
    } catch (Exception $e) {
        error_log('Delete competitor error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function handleImageUpload($file, $type) {
    try {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('Image size too large. Maximum 5MB allowed.');
        }
        
        $upload_dir = '../assets/uploads/competitors/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $type . '_' . uniqid() . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => true, 'path' => 'assets/uploads/competitors/' . $filename];
        } else {
            throw new Exception('Failed to upload image');
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get all competitors
$competitors = [];
try {
    $stmt = $conn->prepare("SELECT * FROM competitor_info ORDER BY created_at DESC");
    $stmt->execute();
    $competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Fetch competitors error: ' . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-users me-2"></i>Competitor Management</h2>
                    <p class="text-muted mb-0">Register and manage competitors for events</p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetitorModal">
                        <i class="fas fa-plus me-2"></i>Add New Competitor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Competitors List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Competitors (<?php echo count($competitors); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($competitors)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">No Competitors Registered</h4>
                            <p class="text-muted">Start by adding your first competitor to the system.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetitorModal">
                                <i class="fas fa-plus me-2"></i>Add First Competitor
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th></th>
                                        <th>Photo</th>
                                        <th>Name & Details</th>
                                        <th>Fighting Style</th>
                                        <th>Record</th>
                                        <th>Win Rate</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                    <?php foreach ($competitors as $competitor): ?>
                                        <tr class="competitor-row" data-competitor='<?php echo htmlspecialchars(json_encode($competitor)); ?>' style="cursor: pointer;" onclick="showCompetitorDetails(<?php echo htmlspecialchars(json_encode($competitor)); ?>)">
                                            <!-- Photo Column -->
                                            <td class="text-center">
                                                <?php if (!empty($competitor['profile_image'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($competitor['profile_image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($competitor['name']); ?>" 
                                                         class="competitor-thumb">
                                                <?php else: ?>
                                                    <div class="competitor-thumb-placeholder">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Name & Details Column -->
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($competitor['name']); ?></strong>
                                                    <?php if (!empty($competitor['weight'])): ?>
                                                        <br><small class="text-muted">Weight: <?php echo htmlspecialchars($competitor['weight']); ?> KG</small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($competitor['nationality'])): ?>
                                                        <br><small class="badge bg-info text-white"><?php echo htmlspecialchars($competitor['nationality']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <!-- Fighting Style Column -->
                                            <td>
                                                <span class="fw-bold"><?php echo htmlspecialchars($competitor['fighting_style'] ?? 'N/A'); ?></span>
                                                <?php if (!empty($competitor['experience_years'])): ?>
                                                    <br><small class="text-muted"><?php echo $competitor['experience_years']; ?> years exp.</small>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Record Column -->
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <span class="badge bg-success"><?php echo $competitor['wins']; ?>W</span>
                                                    <span class="badge bg-danger"><?php echo $competitor['losses']; ?>L</span>
                                                    <?php if ($competitor['draws'] > 0): ?>
                                                        <span class="badge bg-warning text-dark"><?php echo $competitor['draws']; ?>D</span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted"><?php echo ($competitor['total_fights'] ?? 0); ?> total</small>
                                            </td>
                                            <!-- Win Rate Column -->
                                            <td>
                                                <strong><?php echo number_format($competitor['win_rate'], 1); ?>%</strong>
                                            </td>
                                            <!-- Status Column -->
                                            <td>
                                                <?php
                                                    $status_colors = [
                                                        'active' => 'success',
                                                        'inactive' => 'warning',
                                                        'retired' => 'secondary'
                                                    ];
                                                    $color = $status_colors[$competitor['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($competitor['status']); ?></span>
                                            </td>
                                            <!-- Actions Column -->
                                            <td onclick="event.stopPropagation();">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editCompetitor(<?php echo $competitor['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="deleteCompetitor(<?php echo $competitor['id']; ?>, '<?php echo htmlspecialchars($competitor['name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Competitor Modal -->
<div class="modal fade" id="addCompetitorModal" tabindex="-1" aria-labelledby="addCompetitorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_competitor">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCompetitorModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Add New Competitor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="nickname" class="form-label">Nickname</label>
                                <input type="text" class="form-control" id="nickname" name="nickname">
                            </div>
                            <div class="mb-3">
                                <label for="age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="age" name="age" min="16" max="60">
                            </div>
                            <div class="mb-3">
                                <label for="height" class="form-label">Height</label>
                                <input type="text" class="form-control" id="height" name="height" placeholder="e.g., 6'2&quot;">
                            </div>
                            <div class="mb-3">
                                <label for="weight" class="form-label">Weight</label>
                                <input type="text" class="form-control" id="weight" name="weight" placeholder="e.g., 185 lbs">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nationality" class="form-label">Nationality</label>
                                <input type="text" class="form-control" id="nationality" name="nationality">
                            </div>
                            <div class="mb-3">
                                <label for="fighting_style" class="form-label">Fighting Style</label>
                                <input type="text" class="form-control" id="fighting_style" name="fighting_style" placeholder="e.g., Mixed Martial Arts">
                            </div>
                            <div class="mb-3">
                                <label for="experience_years" class="form-label">Experience (Years)</label>
                                <input type="number" class="form-control" id="experience_years" name="experience_years" min="0" max="50">
                            </div>
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                <div class="form-text">Max 5MB. Supported: JPEG, PNG, GIF, WebP</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="bio" class="form-label">Biography</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="achievements" class="form-label">Achievements</label>
                        <textarea class="form-control" id="achievements" name="achievements" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Register Competitor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Competitor Modal -->
<div class="modal fade" id="editCompetitorModal" tabindex="-1" aria-labelledby="editCompetitorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_competitor">
                <input type="hidden" name="competitor_id" id="edit_competitor_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCompetitorModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Competitor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_nickname" class="form-label">Nickname</label>
                                <input type="text" class="form-control" id="edit_nickname" name="nickname">
                            </div>
                            <div class="mb-3">
                                <label for="edit_age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="edit_age" name="age" min="16" max="60">
                            </div>
                            <div class="mb-3">
                                <label for="edit_height" class="form-label">Height</label>
                                <input type="text" class="form-control" id="edit_height" name="height" placeholder="e.g., 6'2&quot;">
                            </div>
                            <div class="mb-3">
                                <label for="edit_weight" class="form-label">Weight</label>
                                <input type="text" class="form-control" id="edit_weight" name="weight" placeholder="e.g., 185 lbs">
                            </div>
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_nationality" class="form-label">Nationality</label>
                                <input type="text" class="form-control" id="edit_nationality" name="nationality">
                            </div>
                            <div class="mb-3">
                                <label for="edit_fighting_style" class="form-label">Fighting Style</label>
                                <input type="text" class="form-control" id="edit_fighting_style" name="fighting_style">
                            </div>
                            <div class="mb-3">
                                <label for="edit_experience_years" class="form-label">Experience (Years)</label>
                                <input type="number" class="form-control" id="edit_experience_years" name="experience_years" min="0" max="50">
                            </div>
                            <div class="mb-3">
                                <label for="edit_profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="edit_profile_image" name="profile_image" accept="image/*">
                                <div class="form-text">Leave empty to keep current image</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_total_fights" class="form-label">Total Fights</label>
                                <input type="number" class="form-control" id="edit_total_fights" name="total_fights" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_wins" class="form-label">Wins</label>
                                <input type="number" class="form-control" id="edit_wins" name="wins" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_losses" class="form-label">Losses</label>
                                <input type="number" class="form-control" id="edit_losses" name="losses" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_draws" class="form-label">Draws</label>
                                <input type="number" class="form-control" id="edit_draws" name="draws" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_bio" class="form-label">Biography</label>
                        <textarea class="form-control" id="edit_bio" name="bio" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_achievements" class="form-label">Achievements</label>
                        <textarea class="form-control" id="edit_achievements" name="achievements" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Competitor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCompetitorModal" tabindex="-1" aria-labelledby="deleteCompetitorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete_competitor">
                <input type="hidden" name="competitor_id" id="delete_competitor_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteCompetitorModalLabel">
                        <i class="fas fa-trash me-2"></i>Delete Competitor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete competitor <strong id="delete_competitor_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. The competitor's profile image will also be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Competitor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Competitor Details Modal -->
<div class="modal fade" id="competitorDetailsModal" tabindex="-1" aria-labelledby="competitorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <div class="d-flex align-items-center">
                    <div class="modal-competitor-img me-3" id="detailsModalCompetitorImg">
                        <i class="fas fa-user fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-1" id="competitorDetailsModalLabel">Competitor Details</h5>
                        <small class="opacity-75" id="detailsModalNickname"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Profile Section -->
                    <div class="col-md-4">
                        <div class="text-center mb-4">
                            <div class="competitor-large-profile-image mb-3" id="detailsLargeImage">
                                <i class="fas fa-user fa-4x text-muted"></i>
                            </div>
                            <h4 id="detailsCompetitorName">-</h4>
                            <p class="text-muted mb-0" id="detailsCompetitorNickname"></p>
                            <span class="badge" id="detailsCompetitorStatus">-</span>
                        </div>
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Basic Info</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted">Age</small>
                                        <div id="detailsAge">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Height</small>
                                        <div id="detailsHeight">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Weight</small>
                                        <div id="detailsWeight">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Nationality</small>
                                        <div id="detailsNationality">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Details Section -->
                    <div class="col-md-8">
                        <!-- Fighting Style & Experience -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-fist-raised me-2"></i>Fighting Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">Fighting Style</small>
                                        <div id="detailsFightingStyle" class="fw-bold">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Experience</small>
                                        <div id="detailsExperience">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fight Record -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-chart-bar me-2"></i>Fight Record</h6>
                                <div class="row text-center">
                                    <div class="col-3">
                                        <div class="border rounded p-2">
                                            <div class="h4 mb-1 text-primary" id="detailsTotalFights">0</div>
                                            <small class="text-muted">Total</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded p-2">
                                            <div class="h4 mb-1 text-success" id="detailsWins">0</div>
                                            <small class="text-muted">Wins</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded p-2">
                                            <div class="h4 mb-1 text-danger" id="detailsLosses">0</div>
                                            <small class="text-muted">Losses</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded p-2">
                                            <div class="h4 mb-1 text-warning" id="detailsDraws">0</div>
                                            <small class="text-muted">Draws</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <div class="h3 text-info mb-0" id="detailsWinRate">0%</div>
                                    <small class="text-muted">Win Rate</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Biography -->
                        <div class="card mb-3" id="detailsBioCard">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-user-edit me-2"></i>Biography</h6>
                                <p id="detailsBio" class="mb-0">-</p>
                            </div>
                        </div>
                        
                        <!-- Achievements -->
                        <div class="card" id="detailsAchievementsCard">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-trophy me-2"></i>Achievements</h6>
                                <p id="detailsAchievements" class="mb-0">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Timestamps -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="row text-center">
                                    <div class="col-md-6">
                                        <small class="text-muted">Created</small>
                                        <div id="detailsCreated">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Last Updated</small>
                                        <div id="detailsUpdated">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editCompetitorFromDetails()">
                    <i class="fas fa-edit me-2"></i>Edit Competitor
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function editCompetitor(competitorId) {
    // Show loading state
    const modal = document.getElementById('editCompetitorModal');
    const modalBody = modal.querySelector('.modal-body');
    const originalContent = modalBody.innerHTML;
    
    modalBody.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><br><small class="text-muted">Loading competitor data...</small></div>';
    
    const editModal = new bootstrap.Modal(modal);
    editModal.show();
    
    // Fetch competitor data via AJAX
    fetch(`../api/get_competitor.php?id=${competitorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const competitor = data.data;
                
                // Restore original modal content
                modalBody.innerHTML = originalContent;
                
                // Populate form fields
                document.getElementById('edit_competitor_id').value = competitor.id;
                document.getElementById('edit_name').value = competitor.name || '';
                document.getElementById('edit_nickname').value = competitor.nickname || '';
                document.getElementById('edit_age').value = competitor.age || '';
                document.getElementById('edit_height').value = competitor.height || '';
                document.getElementById('edit_weight').value = competitor.weight || '';
                document.getElementById('edit_nationality').value = competitor.nationality || '';
                document.getElementById('edit_fighting_style').value = competitor.fighting_style || '';
                document.getElementById('edit_experience_years').value = competitor.experience_years || '';
                document.getElementById('edit_total_fights').value = competitor.total_fights || 0;
                document.getElementById('edit_wins').value = competitor.wins || 0;
                document.getElementById('edit_losses').value = competitor.losses || 0;
                document.getElementById('edit_draws').value = competitor.draws || 0;
                document.getElementById('edit_bio').value = competitor.bio || '';
                document.getElementById('edit_achievements').value = competitor.achievements || '';
                document.getElementById('edit_status').value = competitor.status || 'active';
                
                console.log('Competitor data loaded:', competitor);
            } else {
                modalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading competitor data: ' + (data.error || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error fetching competitor data:', error);
            modalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading competitor data. Please try again.</div>';
        });
}

function deleteCompetitor(id, name) {
    document.getElementById('delete_competitor_id').value = id;
    document.getElementById('delete_competitor_name').textContent = name;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteCompetitorModal'));
    deleteModal.show();
}

// Global variable to store current competitor for details modal
let currentDetailsCompetitor = null;

function showCompetitorDetails(competitor) {
    currentDetailsCompetitor = competitor;
    
    // Set basic info
    document.getElementById('competitorDetailsModalLabel').textContent = competitor.name || 'Unknown Competitor';
    document.getElementById('detailsCompetitorName').textContent = competitor.name || '-';
    document.getElementById('detailsCompetitorNickname').textContent = competitor.nickname ? `"${competitor.nickname}"` : '';
    document.getElementById('detailsModalNickname').textContent = competitor.nickname ? `"${competitor.nickname}"` : '';
    
    // Set status with appropriate color
    const statusElement = document.getElementById('detailsCompetitorStatus');
    const statusColors = {
        'active': 'bg-success',
        'inactive': 'bg-warning',
        'retired': 'bg-secondary'
    };
    statusElement.className = `badge ${statusColors[competitor.status] || 'bg-secondary'}`;
    statusElement.textContent = competitor.status ? competitor.status.charAt(0).toUpperCase() + competitor.status.slice(1) : 'Unknown';
    
    // Set profile images
    setCompetitorProfileImages(competitor.profile_image);
    
    // Set basic info
    document.getElementById('detailsAge').textContent = competitor.age || '-';
    document.getElementById('detailsHeight').textContent = competitor.height || '-';
    document.getElementById('detailsWeight').textContent = competitor.weight || '-';
    document.getElementById('detailsNationality').textContent = competitor.nationality || '-';
    
    // Set fighting info
    document.getElementById('detailsFightingStyle').textContent = competitor.fighting_style || '-';
    document.getElementById('detailsExperience').textContent = competitor.experience_years ? `${competitor.experience_years} years` : '-';
    
    // Set fight record
    document.getElementById('detailsTotalFights').textContent = competitor.total_fights || '0';
    document.getElementById('detailsWins').textContent = competitor.wins || '0';
    document.getElementById('detailsLosses').textContent = competitor.losses || '0';
    document.getElementById('detailsDraws').textContent = competitor.draws || '0';
    document.getElementById('detailsWinRate').textContent = competitor.win_rate ? `${parseFloat(competitor.win_rate).toFixed(1)}%` : '0%';
    
    // Set biography and achievements
    const bioElement = document.getElementById('detailsBio');
    const bioCard = document.getElementById('detailsBioCard');
    if (competitor.bio && competitor.bio.trim()) {
        bioElement.textContent = competitor.bio;
        bioCard.style.display = 'block';
    } else {
        bioElement.textContent = 'No biography available';
        bioCard.style.display = 'block';
    }
    
    const achievementsElement = document.getElementById('detailsAchievements');
    const achievementsCard = document.getElementById('detailsAchievementsCard');
    if (competitor.achievements && competitor.achievements.trim()) {
        achievementsElement.textContent = competitor.achievements;
        achievementsCard.style.display = 'block';
    } else {
        achievementsElement.textContent = 'No achievements listed';
        achievementsCard.style.display = 'block';
    }
    
    // Set timestamps
    document.getElementById('detailsCreated').textContent = competitor.created_at ? 
        new Date(competitor.created_at).toLocaleString() : '-';
    document.getElementById('detailsUpdated').textContent = competitor.updated_at ? 
        new Date(competitor.updated_at).toLocaleString() : '-';
    
    // Show the modal
    const detailsModal = new bootstrap.Modal(document.getElementById('competitorDetailsModal'));
    detailsModal.show();
}

function setCompetitorProfileImages(imagePath) {
    const headerImg = document.getElementById('detailsModalCompetitorImg');
    const largeImg = document.getElementById('detailsLargeImage');
    
    if (imagePath && imagePath.trim()) {
        // Set header image
        headerImg.innerHTML = '';
        headerImg.style.backgroundImage = `url('../${imagePath}')`;
        headerImg.style.backgroundSize = 'cover';
        headerImg.style.backgroundPosition = 'center';
        headerImg.style.backgroundRepeat = 'no-repeat';
        headerImg.style.width = '50px';
        headerImg.style.height = '50px';
        headerImg.style.borderRadius = '8px';
        
        // Set large image
        largeImg.innerHTML = '';
        largeImg.style.backgroundImage = `url('../${imagePath}')`;
        largeImg.style.backgroundSize = 'cover';
        largeImg.style.backgroundPosition = 'center';
        largeImg.style.backgroundRepeat = 'no-repeat';
    } else {
        // Reset to default icons
        headerImg.innerHTML = '<i class="fas fa-user fa-2x text-white"></i>';
        headerImg.style.backgroundImage = 'none';
        
        largeImg.innerHTML = '<i class="fas fa-user fa-4x text-muted"></i>';
        largeImg.style.backgroundImage = 'none';
    }
}

function editCompetitorFromDetails() {
    if (currentDetailsCompetitor) {
        // Close details modal
        const detailsModal = bootstrap.Modal.getInstance(document.getElementById('competitorDetailsModal'));
        if (detailsModal) {
            detailsModal.hide();
        }
        
        // Open edit modal
        setTimeout(() => {
            editCompetitor(currentDetailsCompetitor);
        }, 300); // Small delay to allow modal to close
    }
}
</script>

<style>
.competitor-thumb {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid #e9ecef;
}

.competitor-thumb-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
}

.table th {
    border-top: none;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}

/* Row hover effects */
.competitor-row:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

/* Modal competitor images */
.modal-competitor-img {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

.competitor-large-profile-image {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 4px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    margin: 0 auto;
    background-color: #f8f9fa;
}

/* Action buttons prevent row click */
.competitor-row .btn-group {
    position: relative;
    z-index: 10;
}

/* Click indicator */
.competitor-row {
    position: relative;
}

.competitor-row::before {
    content: "";
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8em;
    color: #6c757d;
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
    z-index: 1;
}

.competitor-row:hover::before {
    opacity: 1;
}
</style>

<?php require_once '../includes/footer.php'; ?>