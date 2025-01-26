<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class Pitch_Form_Import_Page {
    /**
     * The database table name for pitch suggestions.
     *
     * @var string
     */
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . SF_TABLE_PITCH_SUGGESTIONS;
        $this->handle_import();
    }

    public function display() {
        $this->render_form();
    }

    private function render_form() {

		$import_results = sf_retrieve($_SESSION, 'import_results', false);
		unset($_SESSION['import_results']);

        ?>
		<div class="wrap">
			<p>Faça o upload de um arquivo CSV para importar até 1000 Pitches.</p>

			<form method="post" enctype="multipart/form-data">
				<label for="csv_file">Selecione o arquivo CSV:</label>
				<input type="file" id="csv_file" name="csv_file" accept=".csv" required>
				<button type="submit" class="button-primary">Importar Pitches</button>
				<p class="description">O arquivo deve ter no máximo 1000 linhas e seguir o formato correto.</p>
				<input type="hidden" name="action" value="import_csv">
			</form>

		<?php if ($import_results): ?>
			<div class="<?php echo esc_attr($import_results['status'] === 'success' ? 'updated' : 'error'); ?>">
				<p><?php echo esc_html($import_results['message']); ?></p>
			</div>
			<?php if (!empty($import_results['errors'])): ?>
				<h3>Erros</h3>
				<ul>
					<?php foreach ($import_results['errors'] as $error): ?>
						<li><?php echo esc_html($error); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
		</div>

        <?php
    }

    private function handle_import() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action']) || $_POST['action'] !== 'import_csv') {
            return;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['import_results'] = [
                'status' => 'error',
                'message' => 'Erro ao fazer upload do arquivo.',
            ];
            return;
        }

        $file = $_FILES['csv_file']['tmp_name'];

        if (pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION) !== 'csv') {
            $_SESSION['import_results'] = [
                'status' => 'error',
                'message' => 'O arquivo deve estar no formato CSV.',
            ];
            return;
        }

        $this->process_csv($file);
    }

	private function process_csv($file) {
		global $wpdb;
		$handle = fopen($file, 'r');
		if (!$handle) {
			$_SESSION['import_results'] = [
				'status' => 'error',
				'message' => 'Não foi possível ler o arquivo CSV.',
			];
			return;
		}

		$headers = fgetcsv($handle);
		$required_headers = ['Pillar', 'Category', 'Topic', 'Keyword', 'Pitch', 'Status'];

		// Ajusta os cabeçalhos para casos sem "Status"
		$has_status_column = in_array('Status', $headers);
		if (!$has_status_column) {
			$headers[] = 'Status';
		}

		if (array_diff($required_headers, $headers)) {
			$_SESSION['import_results'] = [
				'status' => 'error',
				'message' => 'Os cabeçalhos do CSV não estão corretos. Certifique-se de usar o formato correto.',
			];
			fclose($handle);
			return;
		}

		$row_count = 0;
		$errors = [];
		$success_count = 0;

		while (($row = fgetcsv($handle)) !== false) {
			$row_count++;
			if ($row_count > 1000) {
				$errors[] = 'Limite de 1000 linhas excedido. Apenas as primeiras 1000 linhas foram processadas.';
				break;
			}

			// Completa a linha com valores padrão se estiver faltando colunas
			if (count($row) < count($headers)) {
				$row = array_pad($row, count($headers), ''); // Adiciona strings vazias para preencher as colunas faltantes
			}

			// Mapear os valores da linha com os cabeçalhos
			$data = array_combine($headers, $row);

			// Definir "pending" como padrão para "Status" se não fornecido
			if (!$has_status_column || empty($data['Status'])) {
				$data['Status'] = 'pending';
			}

			// Validação básica dos campos obrigatórios
			if (empty($data['Pillar']) || empty($data['Category']) || empty($data['Pitch'])) {
				$errors[] = "Linha $row_count: Campos obrigatórios (Pillar, Category, Pitch) estão vazios.";
				continue;
			}

			$insert_data = [
				'pillar' => sanitize_text_field($data['Pillar']),
				'category' => sanitize_text_field($data['Category']),
				'topic' => sanitize_text_field($data['Topic']),
				'main_seo_keyword' => sanitize_text_field($data['Keyword']),
				'suggested_pitch' => sanitize_text_field($data['Pitch']),
				'status' => sanitize_text_field($data['Status']),
				'created_at' => current_time('mysql'),
			];

			$inserted = $wpdb->insert($this->table_name, $insert_data);
			if ($inserted === false) {
				$errors[] = "Linha $row_count: Erro ao inserir no banco de dados.";
			} else {
				$success_count++;
			}
		}

		fclose($handle);

		$_SESSION['import_results'] = [
			'status' => empty($errors) ? 'success' : 'error',
			'message' => "Importação concluída: $success_count registros importados com sucesso.",
			'errors' => $errors,
		];
	}
}
