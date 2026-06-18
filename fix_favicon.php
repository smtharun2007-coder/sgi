 <?php
// Script to fix favicon type in all PHP files
$files = glob('*.php');
$fixed = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $newContent = str_replace(
        'rel="icon" type="image/png"',
        'rel="icon" type="image/png"',
        $content,
        $count
    );
    
    if ($count > 0) {
        file_put_contents($file, $newContent);
        $fixed++;
        echo "Fixed: $file ($count replacements)\n";
    }
}

echo "\nTotal files fixed: $fixed\n";
?>