<?php
$txt = file_get_contents('/tmp/la-hacienda-v5.txt');
$rows = \App\Models\ConfiguracionBot::where('tenant_id', 1)->update(['system_prompt' => $txt]);
echo "Rows actualizadas: $rows | bytes: " . strlen($txt) . PHP_EOL;
