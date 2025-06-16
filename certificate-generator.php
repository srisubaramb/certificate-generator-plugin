<?php
/**
 * Plugin Name: Certificate Generator
 * Plugin URI:  https://www.github.com/srisubaramb/certificate-generator-plugin
 * Description: Generates image certificates via a form, stores them in a CPT, provides validation URLs, overlays QR code, lists certificates in admin (with Delete + Donate), and maps text to your template coordinates.
 * Version:     1.2
 * Author:      srisubaram
 * Author URI:  srisubaramb.github.io
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include QR library
require_once plugin_dir_path(__FILE__) . 'phpqrcode/qrlib.php';

/*------------------------------------------------------
| 1. Register Certificate CPT (hidden menu)
------------------------------------------------------*/
function cg_register_certificate_cpt() {
    register_post_type('cg_certificate', [
        'labels'       => [
            'name'          => 'Certificates',
            'singular_name' => 'Certificate',
        ],
        'public'       => false,
        'show_ui'      => false,
        'supports'     => ['title'],
    ]);
}
add_action('init','cg_register_certificate_cpt');

/*------------------------------------------------------
| 2. Frontend Shortcode – Certificate Form
------------------------------------------------------*/
function cg_certificate_form_shortcode() {
    ob_start(); ?>
    <form method="post" style="max-width:400px;margin:2em auto;">
      <p><label>Your Name:</label><br>
         <input type="text" name="cg_name" required style="width:100%;"></p>
      <p><label>Course:</label><br>
         <input type="text" name="cg_course" required style="width:100%;"></p>
      <p><label>Date:</label><br>
         <input type="date" name="cg_date" required style="width:100%;"></p>
      <p><input type="submit" name="cg_submit" value="Generate Certificate" class="button button-primary"></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('certificate_form','cg_certificate_form_shortcode');

/*------------------------------------------------------
| 3. Process Submission → CPT + Redirect
------------------------------------------------------*/
function cg_process_certificate() {
    if ( ! empty($_POST['cg_submit']) ) {
        $name   = sanitize_text_field($_POST['cg_name']   ?? '');
        $course = sanitize_text_field($_POST['cg_course'] ?? '');
        $date   = sanitize_text_field($_POST['cg_date']   ?? '');
        if ( ! $name || ! $course || ! $date ) return;

        // Unique ID
        $random  = strtoupper(substr(bin2hex(random_bytes(5)),0,10));
        $cert_id = 'CERT-' . date('Ymd') . '-' . $random;

        // Insert
        $pid = wp_insert_post([
            'post_type'   => 'cg_certificate',
            'post_title'  => $cert_id,
            'post_status' => 'publish',
            'meta_input'  => [
                'cg_name'   => $name,
                'cg_course' => $course,
                'cg_date'   => $date,
            ],
        ]);
        if ( is_wp_error($pid) ) {
            wp_die('Error generating certificate.');
        }
        wp_redirect( site_url("/certificates/{$cert_id}") );
        exit;
    }
}
add_action('init','cg_process_certificate');

/*------------------------------------------------------
| 4. Rewrite Rules & Query Var
------------------------------------------------------*/
function cg_add_certificate_rewrite() {
    add_rewrite_rule('^certificates/([A-Z0-9\-]+)/?$', 'index.php?certificate_id=$matches[1]', 'top');
}
add_action('init','cg_add_certificate_rewrite');

function cg_certificate_query_vars($vars){
    $vars[] = 'certificate_id';
    return $vars;
}
add_filter('query_vars','cg_certificate_query_vars');

/*------------------------------------------------------
| 5. Draw Centered Text Helper
------------------------------------------------------*/
function cg_draw_text_centered($im,$size,$angle,$y,$color,$font,$text){
    $box = imagettfbbox($size,$angle,$font,$text);
    $w   = abs($box[2]-$box[0]);
    $x   = (imagesx($im)/2) - ($w/2);
    imagettftext($im,$size,$angle,$x,$y,$color,$font,$text);
}

/*------------------------------------------------------
| 6. Stream Certificate + QR
------------------------------------------------------*/
function cg_stream_certificate_jpeg($name,$course,$date,$cert_id,$dl=false) {
    $tpl   = plugin_dir_path(__FILE__).'certificate-template.jpg';
    $font  = plugin_dir_path(__FILE__).'Poppins-Regular.ttf';

    if ( ! file_exists($tpl) ) wp_die('Template missing.');
    $im = imagecreatefromjpeg($tpl);

    $black = imagecolorallocate($im,0,0,0);
    $grey = imagecolorallocate($im, 128, 128, 128); 
    // Mapped coordinates for your template:
    imagettftext($im, 15, 0, 550, 227, $black, $font, $name);     // Draws $name at X=90, Y=300
    imagettftext($im, 32, 0, 375, 290, $black, $font, $course);   // Draws $course at X=90, Y=450
    imagettftext($im, 15, 0, 570, 346, $black, $font, $date);     // Draws $date at X=90, Y=550
    imagettftext($im, 10, 0, 460, 682, $grey, $font, $cert_id);  // Draws $cert_id at X=90, Y=650
    
    // QR
    ob_start();
    QRcode::png(site_url("/certificates/{$cert_id}"),null,QR_ECLEVEL_L,3,2);
    $qr = ob_get_clean();
    $qrim = imagecreatefromstring($qr);
    if($qrim){
        $qw=imagesx($qrim); $qh=imagesy($qrim);
        imagecopy($im,$qrim,imagesx($im)-$qw-100,imagesy($im)-$qh-100,0,0,$qw,$qh);
        imagedestroy($qrim);
    }

    header('Content-Type:image/jpeg');
    if($dl) header('Content-Disposition:attachment;filename="'.$cert_id.'.jpg"');
    imagejpeg($im,null,90);
    imagedestroy($im);
    exit;
}

/*------------------------------------------------------
| 7. Public Certificate Validator & Display
------------------------------------------------------*/
function cg_certificate_validator_template(){
    $cid = get_query_var('certificate_id');
    if( ! $cid ) return;

    $q = new WP_Query([
        'post_type'=>'cg_certificate',
        'title'=>$cid,'post_status'=>'publish','posts_per_page'=>1
    ]);
    if( ! $q->have_posts() ){
        status_header(404);
        echo '<h1>Certificate Not Found</h1>'; exit;
    }
    $p = $q->posts[0];
    $n = get_post_meta($p->ID,'cg_name',true);
    $c = get_post_meta($p->ID,'cg_course',true);
    $d = get_post_meta($p->ID,'cg_date',true);

    if(isset($_GET['image'])) cg_stream_certificate_jpeg($n,$c,$d,$cid);
    if(isset($_GET['dl']))    cg_stream_certificate_jpeg($n,$c,$d,$cid,true);

    status_header(200);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Certificate '.$cid.'</title></head><body style="text-align:center;">';
    echo '<h1>Certificate Valid</h1>';
    echo "<p>Name: $n</p><p>Course: $c</p><p>Date: $d</p><p>ID: $cid</p>";
    echo '<img src="?image=1" style="max-width:80%;"><p>';
    echo '<a href="?dl=1" class="button" style="background-color: blue; color: white; text-decoration: none; padding: 5px;border-radius: 5px;">Download Certificate</a></p>';
    // Donation button here:
    echo '<p>Support Plugin Author - <a style="text-decoration:none" href="https://www.linkedin.com/in/srisubaramb/">Srisubaram </a> <a href="https://paypal.me/srisubaram" target="_blank" class="button button-secondary" style="background-color: #000; color: white; text-decoration: none; padding: 5px;border-radius: 5px;">Donate❤️</a></p>';
    echo '</body></html>'; exit;
}
add_action('template_redirect','cg_certificate_validator_template');

/*------------------------------------------------------
| 8. Admin Menu + Donate Submenu
------------------------------------------------------*/
function cg_add_admin_menu(){
    add_menu_page('Certificates','Certificates','manage_options','cg_cert_list','cg_render_certificates_page','dashicons-awards',20);
    add_submenu_page('cg_cert_list','All Certificates','All Certificates','manage_options','cg_cert_list','cg_render_certificates_page');
    add_submenu_page('cg_cert_list','Add Certificate','Add Certificate','manage_options','cg_cert_add','cg_render_add_certificate_page');
    add_submenu_page('cg_cert_list','Donate','Donate','manage_options','cg_cert_donate','cg_render_donate_page');
}
add_action('admin_menu','cg_add_admin_menu');

function cg_render_donate_page(){
    echo '<div class="wrap"><h1>Support This Plugin</h1>';
    echo '<p>If you find this useful, please consider donating:</p>';
    echo '<a href="https://paypal.me/srisubaram" target="_blank" class="button button-primary">Donate via PayPal</a>';
    
    echo '</div>';
}

/*------------------------------------------------------
| 9. Admin List & Delete
------------------------------------------------------*/
function cg_render_certificates_page(){
    if(!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>Certificates</h1>';
    // Donation button at top:
    echo '<p><a href="https://paypal.me/srisubaram" target="_blank" class="button button-secondary">Donate ❤️</a></p>';

    if(isset($_GET['delete_cert'])){
        $id=intval($_GET['delete_cert']);
        if(wp_verify_nonce($_GET['_wpnonce'],'cg_delete_cert_'.$id)){
            wp_delete_post($id,true);
            echo '<div class="notice notice-success"><p>Deleted.</p></div>';
        }
    }

    $q=new WP_Query(['post_type'=>'cg_certificate','posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC']);
    if(!$q->have_posts()){ echo '<p>No certificates.</p></div>'; return; }

    echo '<table class="widefat fixed striped"><thead><tr><th>ID</th><th>Name</th><th>Course</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
    while($q->have_posts()): $q->the_post();
        $id = get_the_ID();
        $tid= get_the_title();
        $n  = get_post_meta($id,'cg_name',true);
        $c  = get_post_meta($id,'cg_course',true);
        $d  = get_post_meta($id,'cg_date',true);
        $view = esc_url(site_url("/certificates/{$tid}"));
        $del  = wp_nonce_url(admin_url("admin.php?page=cg_cert_list&delete_cert={$id}"),'cg_delete_cert_'.$id);
        echo "<tr><td>{$tid}</td><td>{$n}</td><td>{$c}</td><td>{$d}</td><td>";
        echo "<a href='{$view}' class='button'>View</a> ";
        echo "<a href='{$del}' onclick=\"return confirm('Delete this?')\" class='button button-danger'>Delete</a>";
        echo "</td></tr>";
    endwhile; wp_reset_postdata();
    echo '</tbody></table></div>';
}

/*------------------------------------------------------
| 10. Add Certificate Form to Admin "Add" Page
------------------------------------------------------*/
function cg_render_add_certificate_page(){
    if(!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>Add Certificate</h1>';
    echo do_shortcode('[certificate_form]');
    echo '</div>';
}

/*------------------------------------------------------
| 11. Flush Rewrites on Activation/Deactivation
------------------------------------------------------*/
function cg_flush_rewrites(){
    cg_register_certificate_cpt();
    cg_add_certificate_rewrite();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__,'cg_flush_rewrites');
register_deactivation_hook(__FILE__,'cg_flush_rewrites');
