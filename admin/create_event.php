<?php
$page_title = 'Create Match - Admin Panel';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/AuditChain.php';

requireRole('admin');

$db = new Database();
$conn = $db->connect();

$error_message = '';
$success_message = '';

// Get available streamers
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'streamer' AND status = 'active' ORDER BY username");
$stmt->execute();
$streamers = $stmt->fetchAll();

// Get available competitors from competitor_info table
$competitors = [];
try {
    $stmt = $conn->prepare("SELECT id, name, nickname, fighting_style, profile_image FROM competitor_info WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $competitors = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching competitors: ' . $e->getMessage());
}

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $game_type = trim($_POST['game_type'] ?? '');
    $competitor_a = trim($_POST['competitor_a'] ?? '');
    $competitor_b = trim($_POST['competitor_b'] ?? '');
    $platform_fee_percent = floatval($_POST['platform_fee_percent'] ?? 7.0);
    $match_start_time = $_POST['match_start_time'] ?? '';
    $assigned_streamers = $_POST['streamers'] ?? [];
    
    // Handle image uploads
    $competitor_a_image = null;
    $competitor_b_image = null;
    $upload_errors = [];
    
    // Function to handle image upload
    function handleImageUpload($file, $competitor_name) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // No file uploaded
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error for {$competitor_name}");
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Invalid image type for {$competitor_name}. Only JPEG, PNG, GIF, and WebP allowed.");
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("Image too large for {$competitor_name}. Maximum 5MB allowed.");
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'competitor_' . time() . '_' . uniqid() . '.' . $extension;
        $upload_path = '../assets/uploads/competitors/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception("Failed to upload image for {$competitor_name}");
        }
        
        return 'assets/uploads/competitors/' . $filename;
    }
    
    // Upload images if provided
    try {
        if (isset($_FILES['competitor_a_image'])) {
            $competitor_a_image = handleImageUpload($_FILES['competitor_a_image'], 'Competitor A');
        }
        if (isset($_FILES['competitor_b_image'])) {
            $competitor_b_image = handleImageUpload($_FILES['competitor_b_image'], 'Competitor B');
        }
    } catch (Exception $e) {
        $upload_errors[] = $e->getMessage();
    }
    
    // Validation
    if (!empty($upload_errors)) {
        $error_message = implode(' ', $upload_errors);
    } elseif (empty($name) || empty($game_type) || empty($competitor_a) || empty($competitor_b)) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($competitor_a === $competitor_b) {
        $error_message = 'Competitor A and B must be different.';
    } elseif ($platform_fee_percent < 0 || $platform_fee_percent > 50) {
        $error_message = 'Platform fee must be between 0% and 50%.';
    } elseif (count($assigned_streamers) < 3) {
        $error_message = 'At least 3 streamers must be assigned to each match.';
    } else {
        try {
            $conn->beginTransaction();
            
            // Create match/event
            $stmt = $conn->prepare("
                INSERT INTO events (name, description, game_type, competitor_a, competitor_b, 
                                  competitor_a_image, competitor_b_image, platform_fee_percent, 
                                  match_start_time, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'accepting_bets')
            ");
            $stmt->execute([
                $name, 
                $description, 
                $game_type, 
                $competitor_a,
                $competitor_b,
                $competitor_a_image,
                $competitor_b_image,
                $platform_fee_percent,
                $match_start_time ?: null,
                getUserId()
            ]);
            
            $event_id = $conn->lastInsertId();
            
            // Assign streamers
            $stmt = $conn->prepare("INSERT INTO event_streamers (event_id, streamer_id) VALUES (?, ?)");
            foreach ($assigned_streamers as $streamer_id) {
                $stmt->execute([$event_id, $streamer_id]);
            }
            
            // Log the event creation in audit chain
            $auditChain = new AuditChain();
            $auditChain->logEventActivity(getUserId(), $event_id, 'event_create', [
                'name' => $name,
                'description' => $description,
                'game_type' => $game_type,
                'competitor_a' => $competitor_a,
                'competitor_b' => $competitor_b,
                'assigned_streamers' => $assigned_streamers,
                'platform_fee_percent' => $platform_fee_percent,
                'match_start_time' => $match_start_time,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            $conn->commit();
            
            $success_message = "Match '{$name}' created successfully! Users can now place bets on {$competitor_a} vs {$competitor_b}. Event ID: {$event_id}";
            
            // Clear form
            $_POST = [];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = 'An error occurred while creating the match. Please try again.';
            error_log('Match creation error: ' . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Admin</a></li>
                    <li class="breadcrumb-item active">Create Match</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-plus me-2"></i>Create New Match</h4>
                    <small class="text-muted">Set up a competition between two competitors for users to bet on</small>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            <div class="mt-2">
                                <a href="<?php echo url('admin/events.php'); ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-list me-1"></i>View All Matches
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="location.reload()">
                                    <i class="fas fa-plus me-1"></i>Create Another
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="name" class="form-label">Match Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   placeholder="e.g. Championship Final, League Match #5" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="competitor_a" class="form-label">Competitor A <span class="text-danger">*</span></label>
                                <?php if (empty($competitors)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No competitors available. <a href="<?php echo url('admin/competitors.php'); ?>" target="_blank">Register competitors first</a>.
                                    </div>
                                    <input type="text" class="form-control" id="competitor_a" name="competitor_a" 
                                           value="<?php echo htmlspecialchars($_POST['competitor_a'] ?? ''); ?>" 
                                           placeholder="Enter competitor name manually" required>
                                <?php else: ?>
                                    <select class="form-select" id="competitor_a" name="competitor_a" required>
                                        <option value="">Select Competitor A</option>
                                        <?php foreach ($competitors as $competitor): ?>
                                            <option value="<?php echo htmlspecialchars($competitor['name']); ?>" 
                                                    data-id="<?php echo $competitor['id']; ?>"
                                                    data-image="<?php echo htmlspecialchars($competitor['profile_image'] ?? ''); ?>"
                                                    data-style="<?php echo htmlspecialchars($competitor['fighting_style'] ?? ''); ?>"
                                                    <?php echo ($_POST['competitor_a'] ?? '') === $competitor['name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($competitor['name']); ?>
                                                <?php if (!empty($competitor['nickname'])): ?>
                                                    "<?php echo htmlspecialchars($competitor['nickname']); ?>"
                                                <?php endif; ?>
                                                <?php if (!empty($competitor['fighting_style'])): ?>
                                                    - <?php echo htmlspecialchars($competitor['fighting_style']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <small class="form-text text-muted">Select the first competitor</small>
                                <div id="competitor_a_info" class="mt-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="competitor_b" class="form-label">Competitor B <span class="text-danger">*</span></label>
                                <?php if (empty($competitors)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No competitors available. <a href="<?php echo url('admin/competitors.php'); ?>" target="_blank">Register competitors first</a>.
                                    </div>
                                    <input type="text" class="form-control" id="competitor_b" name="competitor_b" 
                                           value="<?php echo htmlspecialchars($_POST['competitor_b'] ?? ''); ?>" 
                                           placeholder="Enter competitor name manually" required>
                                <?php else: ?>
                                    <select class="form-select" id="competitor_b" name="competitor_b" required>
                                        <option value="">Select Competitor B</option>
                                        <?php foreach ($competitors as $competitor): ?>
                                            <option value="<?php echo htmlspecialchars($competitor['name']); ?>" 
                                                    data-id="<?php echo $competitor['id']; ?>"
                                                    data-image="<?php echo htmlspecialchars($competitor['profile_image'] ?? ''); ?>"
                                                    data-style="<?php echo htmlspecialchars($competitor['fighting_style'] ?? ''); ?>"
                                                    <?php echo ($_POST['competitor_b'] ?? '') === $competitor['name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($competitor['name']); ?>
                                                <?php if (!empty($competitor['nickname'])): ?>
                                                    "<?php echo htmlspecialchars($competitor['nickname']); ?>"
                                                <?php endif; ?>
                                                <?php if (!empty($competitor['fighting_style'])): ?>
                                                    - <?php echo htmlspecialchars($competitor['fighting_style']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <small class="form-text text-muted">Select the second competitor</small>
                                <div id="competitor_b_info" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <!-- Competitor Images (Optional Override) -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="competitor_a_image" class="form-label">Competitor A Custom Image (Optional)</label>
                                <input type="file" class="form-control" id="competitor_a_image" name="competitor_a_image" 
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="form-text text-muted">Optional override. Leave empty to use competitor's profile image. Max 5MB.</small>
                                <div id="competitor_a_preview" class="mt-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="competitor_b_image" class="form-label">Competitor B Custom Image (Optional)</label>
                                <input type="file" class="form-control" id="competitor_b_image" name="competitor_b_image" 
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="form-text text-muted">Optional override. Leave empty to use competitor's profile image. Max 5MB.</small>
                                <div id="competitor_b_preview" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="game_type" class="form-label">Game/Sport Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="game_type" name="game_type" required>
                                    <option value="">Select Game Type</option>
                                    <option value="FPS" <?php echo ($_POST['game_type'] ?? '') === 'FPS' ? 'selected' : ''; ?>>First Person Shooter</option>
                                    <option value="MOBA" <?php echo ($_POST['game_type'] ?? '') === 'MOBA' ? 'selected' : ''; ?>>MOBA (League/Dota)</option>
                                    <option value="RTS" <?php echo ($_POST['game_type'] ?? '') === 'RTS' ? 'selected' : ''; ?>>Real-Time Strategy</option>
                                    <option value="Fighting" <?php echo ($_POST['game_type'] ?? '') === 'Fighting' ? 'selected' : ''; ?>>Fighting Game</option>
                                    <option value="Racing" <?php echo ($_POST['game_type'] ?? '') === 'Racing' ? 'selected' : ''; ?>>Racing</option>
                                    <option value="Sports" <?php echo ($_POST['game_type'] ?? '') === 'Sports' ? 'selected' : ''; ?>>Sports Simulation</option>
                                    <option value="Football" <?php echo ($_POST['game_type'] ?? '') === 'Football' ? 'selected' : ''; ?>>Football</option>
                                    <option value="Basketball" <?php echo ($_POST['game_type'] ?? '') === 'Basketball' ? 'selected' : ''; ?>>Basketball</option>
                                    <option value="Esports" <?php echo ($_POST['game_type'] ?? '') === 'Esports' ? 'selected' : ''; ?>>General Esports</option>
                                    <option value="Other" <?php echo ($_POST['game_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="platform_fee_percent" class="form-label">Platform Fee (%)</label>
                                <input type="number" class="form-control" id="platform_fee_percent" name="platform_fee_percent" 
                                       min="0" max="50" step="0.1" value="<?php echo htmlspecialchars($_POST['platform_fee_percent'] ?? '7.0'); ?>">
                                <small class="form-text text-muted">Fee taken from winnings</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Describe the match, rules, or any important information..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="match_start_time" class="form-label">Match Start Time</label>
                            <input type="datetime-local" class="form-control" id="match_start_time" name="match_start_time" 
                                   value="<?php echo htmlspecialchars($_POST['match_start_time'] ?? ''); ?>">
                            <small class="form-text text-muted">When the actual match/competition will begin</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="streamers" class="form-label">Assign Streamers <span class="text-danger">*</span></label>
                            <small class="form-text text-muted d-block mb-2">Select at least 3 streamers to verify results</small>
                            
                            <?php if (empty($streamers)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No streamers available. You need to create streamer accounts first.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($streamers as $streamer): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="streamers[]" 
                                                       value="<?php echo $streamer['id']; ?>" id="streamer_<?php echo $streamer['id']; ?>"
                                                       <?php echo in_array($streamer['id'], $_POST['streamers'] ?? []) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="streamer_<?php echo $streamer['id']; ?>">
                                                    <?php echo htmlspecialchars($streamer['username']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>How Betting Works:</h6>
                            <ul class="mb-0">
                                <li>Users choose which competitor (A or B) will win</li>
                                <li>Users set their own bet amount (within limits)</li>
                                <li>Bets are matched with opposing users betting the same amount on the other competitor</li>
                                <li>Only matched bets participate - unmatched bets are refunded</li>
                                <li>Winners get opponent's bet amount minus platform fee</li>
                                <li>Results verified by assigned streamers through voting</li>
                            </ul>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('admin/index.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary" <?php echo empty($streamers) ? 'disabled' : ''; ?>>
                                <i class="fas fa-plus me-2"></i>Create Match
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Competitor data for dynamic filtering
const competitors = <?php echo json_encode($competitors); ?>;

// Dynamic dropdown filtering - disable selected competitor in other dropdown
function updateCompetitorOptions() {
    const competitorA = document.getElementById('competitor_a');
    const competitorB = document.getElementById('competitor_b');
    
    if (!competitorA || !competitorB) return; // Exit if dropdowns don't exist
    
    const selectedA = competitorA.value;
    const selectedB = competitorB.value;
    
    // Reset all options to enabled first
    resetAllOptions(competitorA);
    resetAllOptions(competitorB);
    
    // Disable selected competitor in the other dropdown
    if (selectedA && selectedA !== '') {
        disableOptionInDropdown(competitorB, selectedA);
    }
    
    if (selectedB && selectedB !== '') {
        disableOptionInDropdown(competitorA, selectedB);
    }
}

function resetAllOptions(dropdown) {
    const options = dropdown.querySelectorAll('option');
    options.forEach(option => {
        if (option.value !== '') { // Don't modify the default "Select" option
            option.disabled = false;
            option.style.display = '';
        }
    });
}

function disableOptionInDropdown(dropdown, valueToDisable) {
    const optionToDisable = dropdown.querySelector(`option[value="${valueToDisable.replace(/"/g, '\\"')}"]`);
    if (optionToDisable && optionToDisable.value !== '') {
        optionToDisable.disabled = true;
        // Optionally hide the option (some browsers show disabled options differently)
        optionToDisable.style.display = 'none';
    }
}

function showCompetitorInfo(competitorName, infoElementId) {
    const infoElement = document.getElementById(infoElementId);
    
    if (!competitorName || !infoElement) {
        if (infoElement) infoElement.innerHTML = '';
        return;
    }
    
    const competitor = competitors.find(c => c.name === competitorName);
    
    if (!competitor) {
        infoElement.innerHTML = '';
        return;
    }
    
    let imageHtml = '';
    if (competitor.profile_image) {
        imageHtml = `<img src="../${competitor.profile_image}" alt="${competitor.name}" 
                          class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;">`;
    } else {
        imageHtml = `<div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                          style="width: 60px; height: 60px; color: #6c757d;">
                         <i class="fas fa-user fa-2x"></i>
                     </div>`;
    }
    
    infoElement.innerHTML = `
        <div class="card bg-light">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    ${imageHtml}
                    <div>
                        <h6 class="mb-1">${competitor.name}</h6>
                        ${competitor.nickname ? `<small class="text-muted">"${competitor.nickname}"</small><br>` : ''}
                        ${competitor.fighting_style ? `<small class="badge bg-secondary">${competitor.fighting_style}</small>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Image preview functionality
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="position-relative" style="max-width: 200px;">
                    <img src="${e.target.result}" class="img-thumbnail" style="max-width: 100%; height: auto;">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" 
                            onclick="clearImage('${input.id}', '${previewId}')" 
                            style="transform: translate(50%, -50%);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = '';
    }
}

function clearImage(inputId, previewId) {
    document.getElementById(inputId).value = '';
    document.getElementById(previewId).innerHTML = '';
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Competitor dropdown change handlers
    const competitorA = document.getElementById('competitor_a');
    const competitorB = document.getElementById('competitor_b');
    
    if (competitorA) {
        competitorA.addEventListener('change', function() {
            updateCompetitorOptions();
            showCompetitorInfo(this.value, 'competitor_a_info');
        });
        
        // Show initial info if value exists
        if (competitorA.value) {
            showCompetitorInfo(competitorA.value, 'competitor_a_info');
        }
    }
    
    if (competitorB) {
        competitorB.addEventListener('change', function() {
            updateCompetitorOptions();
            showCompetitorInfo(this.value, 'competitor_b_info');
        });
        
        // Show initial info if value exists  
        if (competitorB.value) {
            showCompetitorInfo(competitorB.value, 'competitor_b_info');
        }
    }
    
    // Image preview handlers
    const competitorAImage = document.getElementById('competitor_a_image');
    const competitorBImage = document.getElementById('competitor_b_image');
    
    if (competitorAImage) {
        competitorAImage.addEventListener('change', function() {
            previewImage(this, 'competitor_a_preview');
        });
    }
    
    if (competitorBImage) {
        competitorBImage.addEventListener('change', function() {
            previewImage(this, 'competitor_b_preview');
        });
    }
    
    // Initial setup
    updateCompetitorOptions();
});
</script>

<?php require_once '../includes/footer.php'; ?>