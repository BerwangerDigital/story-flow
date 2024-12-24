<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_Form_Page {

	public function display() {
        // if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prompt'])) {
        //     $this->handle_form_submission();
        // }

        $this->render_form();
    }

	private function render_form() { ?>

		<table class="form-table">
			<tr>
		<th scope="row">
			<label for="default_prompt"><?php echo esc_html__( 'Default Prompt', 'story-flow' ); ?></label>
		</th>
		<td>
			<textarea name="default_prompt" id="default_prompt" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $default_prompt ); ?></textarea>
			<p class="description" id="tagline-description">Informe como você gostaria que a IA respondesse</p>
		</td>
		</tr>
		</table>
		<p class="submit"><button type="submit" name="submit" class="button-primary"><?php echo esc_html__( 'Save Changes', 'story-flow' ); ?></button></p>

		<?php
	}
}
