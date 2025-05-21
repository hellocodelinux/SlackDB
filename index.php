<?php
// Function to find packages that require the searched software
function findPackagesThatRequire($targetPackageName, $allParsedPackages) {
    $requiredBy = [];
    foreach ($allParsedPackages as $packageName => $packageData) {
        if (isset($packageData['requires']) && $packageData['requires'] !== 'Not specified') {
            // Check if the current package's 'requires' field contains the targetPackageName
            if (preg_match('/\b' . preg_quote($targetPackageName, '/') . '\b/', $packageData['requires'])) {
                $requiredBy[] = $packageName; // Add the name of the package that requires it
            }
        }
    }
    return $requiredBy;
}

// Function to generate URLs to the SlackBuilds.org repository
function generateSboDirectoryUrl($location_string) {
    if (empty($location_string)) {
        return '';
    }
    $sbo_base_url = 'https://slackbuilds.org/repository/15.0';
    $clean_path = ltrim($location_string, './'); // Remove ./ or . at the beginning
    if (!$clean_path) {
        return rtrim($sbo_base_url, '/') . '/';
    }
    // Ensure a single slash between base and path, and a trailing slash
    return rtrim($sbo_base_url, '/') . '/' . trim($clean_path, '/') . '/';
}

// Function to parse a SlackBuild entry
function parseSlackbuildEntry($slackbuild_content) {
    $packageData = [
        'name' => '', 'version' => 'Not specified', 'description' => 'Not available',
        'requires' => 'Not specified', 'location' => '', 'files' => '',
        'download' => '', 'download_x86_64' => '', 'md5sum' => '', 'md5sum_x86_64' => '',
    ];

    preg_match('/SLACKBUILD NAME: (.+)/m', $slackbuild_content, $name_match);
    if (empty($name_match[1])) return null;
    $packageData['name'] = trim($name_match[1]);

    $getField = function($pattern) use ($slackbuild_content) {
        preg_match($pattern, $slackbuild_content, $match);
        return isset($match[1]) ? trim($match[1]) : null;
    };

    $packageData['version'] = $getField('/SLACKBUILD VERSION: (.+)/m') ?? 'Not specified';
    $packageData['description'] = $getField('/SLACKBUILD SHORT DESCRIPTION: (.+)/m') ?? 'Not available';
    $packageData['requires'] = $getField('/SLACKBUILD REQUIRES: (.+)/m') ?? 'Not specified';
    $packageData['location'] = $getField('/SLACKBUILD LOCATION: (.+)/m') ?? '';
    $packageData['files'] = $getField('/SLACKBUILD FILES: (.+)/m') ?? '';
    $packageData['download'] = $getField('/SLACKBUILD DOWNLOAD: (.+)/m') ?? '';
    $packageData['download_x86_64'] = $getField('/SLACKBUILD DOWNLOAD_x86_64: (.+)/m') ?? '';
    $packageData['md5sum'] = $getField('/SLACKBUILD MD5SUM: (.+)/m') ?? '';
    $packageData['md5sum_x86_64'] = $getField('/SLACKBUILD MD5SUM_x86_64: (.+)/m') ?? '';
    
    return $packageData;
}

// Load the file only once
$file = 'SLACKBUILDS.TXT';
$content = file_exists($file) ? file_get_contents($file) : '';
$slackbuild_entries_text = $content ? explode("\n\n", $content) : [];

$parsed_slackbuilds_map = [];
foreach ($slackbuild_entries_text as $entry_text) {
    $data = parseSlackbuildEntry($entry_text);
    if ($data && !empty($data['name'])) {
        $parsed_slackbuilds_map[$data['name']] = $data;
    }
}

$searchTerm = $_GET['q'] ?? '';
$searchTerm = trim($searchTerm);

$foundPackagesToDisplay = [];

if ($searchTerm) {
    foreach ($parsed_slackbuilds_map as $packageNameKey => $packageData) {
        // Case-insensitive "contains" search
        if (stripos($packageNameKey, $searchTerm) !== false) {
            $currentPackageOutput = $packageData; // Base data for the matched package

            // 1. Populate 'requiresDetails' for the current package
            $currentPackageOutput['requiresDetails'] = [];
            if (isset($packageData['requires']) && $packageData['requires'] && $packageData['requires'] !== 'Not specified') {
                $individualRequirementNames = preg_split('/[\s,]+/', $packageData['requires']);
                $individualRequirementNames = array_filter(array_map('trim', $individualRequirementNames));
                
                $reqDetails = [];
                foreach($individualRequirementNames as $reqName) {
                    if (isset($parsed_slackbuilds_map[$reqName])) {
                        $depPackage = $parsed_slackbuilds_map[$reqName];
                        $reqDetails[] = [
                            'name' => $depPackage['name'],
                            'version' => $depPackage['version'],
                            'description' => $depPackage['description'],
                            'location' => $depPackage['location']
                        ];
                    }
                }
                $currentPackageOutput['requiresDetails'] = $reqDetails;
            }

            // 2. Populate 'requiredByDetails' for the current package
            // Find names of packages that require this $packageNameKey
            $requiredByNames = findPackagesThatRequire($packageNameKey, $parsed_slackbuilds_map);
            
            $reqByDetailsList = [];
            foreach($requiredByNames as $reqByName) {
                 if (isset($parsed_slackbuilds_map[$reqByName])) {
                    $dependentPkg = $parsed_slackbuilds_map[$reqByName];
                    $reqByDetailsList[] = [
                        'name' => $dependentPkg['name'],
                        'version' => $dependentPkg['version'],
                        'description' => $dependentPkg['description'],
                        'location' => $dependentPkg['location']
                    ];
                }
            }
            $currentPackageOutput['requiredByDetails'] = $reqByDetailsList;
            
            $foundPackagesToDisplay[] = $currentPackageOutput;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $searchTerm ? htmlspecialchars($searchTerm) . " - " : ""; ?>SlackBuilds Search</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>
    <main>
    <?php if (!$searchTerm): ?>
    <!-- Main search view (Google style) -->
    <div class="search-container">
        <div class="search-logo">
            <a href="index.php" style="text-decoration: none;">
                <span>S</span><span>l</span><span>a</span><span>c</span><span>k</span><span>DB</span>
            </a>
        </div>
        <form action="" method="GET" class="search-form">
            <input type="text" name="q" class="search-input" placeholder="Search SlackBuild..." autocomplete="off" autofocus>
            <div style="text-align: center;">
                <button type="submit" class="search-button">Search SlackBuilds</button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <!-- Results view with compact search at the top -->
    <div class="compact-search">
        <div class="compact-logo">
            <a href="index.php" style="text-decoration: none;">
                <span style="color: var(--logo-blue);">S</span><span style="color: var(--logo-red);">l</span><span style="color: var(--logo-yellow);">a</span><span style="color: var(--logo-blue);">c</span><span style="color: var(--logo-green);">k</span><span style="color: var(--logo-red);">DB</span>
            </a>
        </div>
        <form action="" method="GET" class="compact-form">
            <input type="text" name="q" class="search-input" value="<?php echo htmlspecialchars($searchTerm); ?>" autocomplete="off">
        </form>
    </div>

    <div class="results-container">
        <?php if (!empty($foundPackagesToDisplay)): ?>
            <div class="result-count">
            </div>
            
            <?php foreach ($foundPackagesToDisplay as $resultItem): ?>
            <div class="result-item">
                <h3 class="result-title">
                    <a href="?q=<?php echo urlencode($resultItem['name']); ?>" style="color: inherit; text-decoration: none;">
                        <?php echo htmlspecialchars($resultItem['name']); ?> <?php echo htmlspecialchars($resultItem['version']); ?>
                    </a>
                </h3>
                
                <?php 
                $sbo_page_url = '';
                if (!empty($resultItem['location'])) {
                    $sbo_page_url = generateSboDirectoryUrl($resultItem['location']);
                }
                ?>
                
                <div class="result-url"><?php echo $sbo_page_url ? '<a href="' . htmlspecialchars($sbo_page_url) . '" target="_blank" style="color: inherit; text-decoration: none;">' . htmlspecialchars($sbo_page_url) . '</a>' : 'slackbuilds.org/repository'; ?></div>
                <div class="result-description"><?php echo htmlspecialchars($resultItem['description']); ?></div>
                
                <div class="details-grid">
                    <?php if (!empty($resultItem['location'])): ?>
                    <div class="details-label">Location:</div>
                    <div class="details-value">
                        <a href="<?php echo htmlspecialchars($sbo_page_url); ?>" target="_blank" style="color: var(--result-title); text-decoration: none;">
                            <?php echo htmlspecialchars($resultItem['location']); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($resultItem['download'])): ?>
                    <div class="details-label">Download:</div>
                    <div class="details-value"><?php echo htmlspecialchars($resultItem['download']); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($resultItem['md5sum'])): ?>
                    <div class="details-label">MD5SUM:</div>
                    <div class="details-value"><?php echo htmlspecialchars($resultItem['md5sum']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($resultItem['md5sum_x86_64']) && trim($resultItem['md5sum_x86_64']) !== ''): ?>
                    <div class="details-label">MD5SUM (x86_64):</div>
                    <div class="details-value"><?php echo htmlspecialchars($resultItem['md5sum_x86_64']); ?></div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($resultItem['requiresDetails'])): ?>
                <div class="result-section">
                    <div class="result-section-title">Requires:</div>
                    <hr style="border: none; border-top: 1px solid var(--separator); margin: 10px 0 20px 0;">
                    <?php foreach ($resultItem['requiresDetails'] as $reqDetailPackage): ?>
                        <div class="result-item">
                            <h3 class="result-title">
                                <a href="?q=<?php echo urlencode($reqDetailPackage['name']); ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($reqDetailPackage['name']); ?> <?php echo htmlspecialchars($reqDetailPackage['version']); ?>
                                </a>
                            </h3>
                            
                            <?php 
                            $req_detail_sbo_url = '';
                            if (!empty($reqDetailPackage['location'])) {
                                $req_detail_sbo_url = generateSboDirectoryUrl($reqDetailPackage['location']);
                            }
                            ?>
                            
                            <div class="result-url"><?php echo $req_detail_sbo_url ? '<a href="' . htmlspecialchars($req_detail_sbo_url) . '" target="_blank" style="color: inherit; text-decoration: none;">' . htmlspecialchars($req_detail_sbo_url) . '</a>' : 'slackbuilds.org/repository'; ?></div>
                            <div class="result-description"><?php echo htmlspecialchars($reqDetailPackage['description']); ?></div>
                            
                            <div class="details-grid">
                                <?php if (!empty($reqDetailPackage['location'])): ?>
                                <div class="details-label">Location:</div>
                                <div class="details-value">
                                    <a href="<?php echo htmlspecialchars($req_detail_sbo_url); ?>" target="_blank" style="color: var(--result-title); text-decoration: none;">
                                        <?php echo htmlspecialchars($reqDetailPackage['location']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php elseif (isset($resultItem['requires']) && $resultItem['requires'] && $resultItem['requires'] !== 'Not specified'): ?>
                <div class="result-section">
                    <div class="result-section-title">Requires:</div>
                    <div class="result-section-content"><?php echo htmlspecialchars($resultItem['requires']); ?> (Individual details not available)</div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($resultItem['requiredByDetails'])): ?>
                <div class="result-section">
                    <div class="result-section-title">This software is required by:</div>
                    <hr style="border: none; border-top: 1px solid var(--separator); margin: 10px 0 20px 0;">
                    <?php foreach ($resultItem['requiredByDetails'] as $reqPackage): ?>
                        <div class="result-item">
                            <h3 class="result-title">
                                <a href="?q=<?php echo urlencode($reqPackage['name']); ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($reqPackage['name']); ?> <?php echo htmlspecialchars($reqPackage['version']); ?>
                                </a>
                            </h3>
                            
                            <?php 
                            $req_by_sbo_url = '';
                            if (!empty($reqPackage['location'])) {
                                $req_by_sbo_url = generateSboDirectoryUrl($reqPackage['location']);
                            }
                            ?>
                            
                            <div class="result-url"><?php echo $req_by_sbo_url ? '<a href="' . htmlspecialchars($req_by_sbo_url) . '" target="_blank" style="color: inherit; text-decoration: none;">' . htmlspecialchars($req_by_sbo_url) . '</a>' : 'slackbuilds.org/repository'; ?></div>
                            <div class="result-description"><?php echo htmlspecialchars($reqPackage['description']); ?></div>
                            
                            <div class="details-grid">
                                <?php if (!empty($reqPackage['location'])): ?>
                                <div class="details-label">Location:</div>
                                <div class="details-value">
                                    <a href="<?php echo htmlspecialchars($req_by_sbo_url); ?>" target="_blank" style="color: var(--result-title); text-decoration: none;">
                                        <?php echo htmlspecialchars($reqPackage['location']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <p>No SlackBuild found containing '<?php echo htmlspecialchars($searchTerm); ?>'.</p>
                <p>Try another search term or check your spelling.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    </main>
    <footer>
        <p>&#x2117; 2024 Eduardo Castillo</p>
    </footer>
</body>
</html>
