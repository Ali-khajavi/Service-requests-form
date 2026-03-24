<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="srf-project-wrapper">

<?php if ( ! empty( $success ) ) : ?>

    <div class="srf-project-success">
        <h2>Submission successful 🎉</h2>
        <p>Your project has been submitted.</p>
        <p>You will be redirected to your dashboard...</p>
    </div>

<?php else : ?>

<form method="post" enctype="multipart/form-data" class="srf-project-form" data-srf-project-form>

    <!-- STEP INDICATOR -->
    <div class="srf-project-steps">
        <div class="step active">1. Details</div>
        <div class="step">2. Upload</div>
        <div class="step">3. Done</div>
    </div>

    <!-- STEP 1 -->
    <div class="srf-step srf-step-1 active">

        <div class="srf-grid">

            <!-- LEFT -->
            <div class="srf-left">
                <label>Project title *</label>
                <input type="text" name="srf_project_title" required>

                <label>Description</label>
                <textarea name="srf_project_description"></textarea>
            </div>

            <!-- RIGHT -->
            <div class="srf-right">
                <?php if ( ! is_user_logged_in() ) : ?>
                    <h3>Login or Register</h3>
                    <?php echo do_shortcode('[woocommerce_my_account]'); ?>
                <?php else : ?>
                    <div class="srf-logged">
                        You are logged in ✔
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <button type="button" class="srf-next">Next</button>

    </div>

    <!-- STEP 2 -->
    <div class="srf-step srf-step-2">

        <label>
            <input type="checkbox" name="srf_terms" required>
            I accept Terms & Conditions
        </label>

        <label>Upload file(s)</label>
        <input type="file" name="srf_files[]" multiple required>

        <div class="srf-buttons">
            <button type="button" class="srf-back">Back</button>
            <button type="submit">Submit</button>
        </div>

        <input type="hidden" name="srf_project_form_submitted" value="1">
        <?php wp_nonce_field( 'srf_submit_project_request', 'srf_project_nonce' ); ?>

    </div>

</form>

<?php endif; ?>

</div>