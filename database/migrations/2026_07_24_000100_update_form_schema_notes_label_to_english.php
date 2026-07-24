<?php

use App\Models\FormVersion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $formVersions = FormVersion::all();
        foreach ($formVersions as $formVersion) {
            $schema = $formVersion->schema;
            if (! is_array($schema)) {
                continue;
            }

            $updated = false;
            foreach ($schema as &$field) {
                if (($field['key'] ?? '') === 'notes') {
                    $field['label'] = 'Notes for Editorial Team';
                    $field['help'] = 'Optional notes or instructions for the editorial team';
                    $updated = true;
                }
            }
            unset($field);

            if ($updated) {
                $formVersion->update(['schema' => $schema]);
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
