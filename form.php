<?php
global $wpdb;
require_once(plugin_dir_path(__FILE__) . 'CSSParserToJson.php');

$message = '';
$jsonOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parser = new CSSParserToJson();

    if (isset($_POST['generate_json'])) {
        $inputCSS = $_POST['css_input'] ?? '';
        $jsonOutput = $parser->parse($inputCSS);
    }

    if (isset($_POST['update_elementor'])) {
        $jsonOutput = $_POST['json_output'] ?? '';
        $jsonOutput  = str_replace('\', '', $jsonOutput);
        $updated = $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $jsonOutput],
            ['meta_key' => '_elementor_global_classes']
        );
        $message = $updated !== false ? 'Elementor global classes updated.' : 'Failed to update.';
    }

    if (isset($_POST['reset'])) {
        $default = '{"items":[],"order":[]}';
        $updated = $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $default],
            ['meta_key' => '_elementor_global_classes']
        );
        $message = $updated !== false ? 'Reset successful.' : 'Failed to reset.';
    }
}
?>
<div class="wrap">
    <h1>CSS to Elementor Global Classes</h1>
    <form method="post">
        <h2>Minified CSS Input:</h2>
        <textarea name="css_input" rows="10" style="width: 100%;"><?php echo esc_textarea($_POST['css_input'] ?? ''); ?></textarea>
        <p><input type="submit" name="generate_json" class="button button-primary" value="Generate JSON"></p>
        <h2>Generated JSON Output:</h2>
        <textarea name="json_output" rows="10" style="width: 100%;"><?php echo esc_textarea($jsonOutput); ?></textarea>
        <p>
            <input type="submit" name="update_elementor" class="button button-secondary" value="Update Elementor Global Classes">
            <input type="submit" name="reset" class="button button-danger" value="Reset" onclick="return confirm('Are you sure?')">
        </p>
    </form>
</div>
