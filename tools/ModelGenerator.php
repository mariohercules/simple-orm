<?php

class ModelGenerator
{
    private $modelDirectory = __DIR__ . '../../app/Models/';
    private $namespace = 'App\\Models';

    public function generate(string $tableName)
    {
        // Remove 'ngb_' prefix and convert to CamelCase
        $className = $this->getClassName($tableName);
        
        $template = <<<PHP
<?php

namespace {$this->namespace};

use SimpleORM\Model;

class {$className} extends Model
{
    protected string \$table = '{$tableName}';
    
    protected array \$fillable = [
        // Add your fillable fields here
    ];
    
    protected array \$guarded = ['id'];
}
PHP;

        $filename = $this->modelDirectory . $className . '.php';
        file_put_contents($filename, $template);
        
        echo "Generated model {$className} for table {$tableName}\n";
    }

    private function getClassName(string $tableName): string
    {
        // Remove 'ngb_' prefix
        $name = str_replace('ngb_', '', $tableName);
        
        // Convert to singular if possible (basic conversion)
        if (substr($name, -1) === 's') {
            $name = substr($name, 0, -1);
        }
        
        // Convert snake_case to CamelCase
        return str_replace('_', '', ucwords($name, '_'));
    }
}

// Usage example
$tables = [
    'ngb_agendas',
    'ngb_alarmes',
    'ngb_alarmes_categoria',
    'ngb_alarmes_escopo',
    'ngb_atendimentos',
    'ngb_ativos',
    'ngb_autorizados',
    'ngb_autorizados_avaliacao',
    'ngb_autorizados_avaliacao_mensal',
    'ngb_capacidade_volumetrica',
    'ngb_checklist',
    'ngb_cilindros',
    'ngb_clientes',
    'ngb_clientes_ativos',
    'ngb_clientes_pco',
    'ngb_defeitonc',
    'ngb_descricao_tecnica',
    'ngb_documentos',
    'ngb_documentos_ativo_imagem',
    'ngb_documentos_imagem',
    'ngb_documentos_pco',
    'ngb_documentos_sgs',
    'ngb_documentos_tecnicos',
    'ngb_documentos_tipo',
    'ngb_empresas',
    'ngb_equipes',
    'ngb_escopo',
    'ngb_escopo_abastecimento',
    'ngb_escopo_avaliacao',
    'ngb_escopo_faturamento',
    'ngb_escopo_material',
    'ngb_escopo_pagamento',
    'ngb_espelho',
    'ngb_espelho_ativo',
    'ngb_espelho_autorizados',
    'ngb_estoque',
    'ngb_estoque_equipamento',
    'ngb_estoque_movimentos',
    'ngb_estoque_tipos',
    'ngb_familia',
    'ngb_faturamento',
    'ngb_hierarquia',
    'ngb_manifold',
    'ngb_material_aplicado',
    'ngb_modelo_carta',
    'ngb_nivel',
    'ngb_options',
    'ngb_ordem_servico',
    'ngb_ordem_servico_ativo',
    'ngb_os_ativo_mat_aplicado',
    'ngb_os_ativo_nconformidade',
    'ngb_os_ativo_servico_tecnico',
    'ngb_os_checklist',
    'ngb_os_checklist_ativo',
    'ngb_os_equipamento',
    'ngb_os_intec',
    'ngb_os_intec_det',
    'ngb_os_mat_aplicado',
    'ngb_os_nconformidade',
    'ngb_os_sendmails',
    'ngb_os_servico_tecnico',
    'ngb_pco_tipo_alarme',
    'ngb_pco_tipo_alarme_motivo',
    'ngb_pco_tipo_caracteristica',
    'ngb_pco_tipo_coletor',
    'ngb_pco_tipo_instalacao',
    'ngb_pco_tipo_ponto',
    'ngb_pco_tipo_posicao',
    'ngb_pco_tipo_rede',
    'ngb_pco_tipo_segmento',
    'ngb_pco_tipo_status',
    'ngb_programacao',
    'ngb_programacao_ativo',
    'ngb_programacao_historico',
    'ngb_projeto',
    'ngb_projeto_atividade',
    'ngb_regional',
    'ngb_regional_detalhes',
    'ngb_tecnicos',
    'ngb_tipo_ativo',
    'ngb_tipo_central',
    'ngb_tipo_cliente',
    'ngb_tipo_defeito',
    'ngb_tipo_equipamento',
    'ngb_tipo_intec',
    'ngb_tipo_os_servico',
    'ngb_tipo_servicos',
    'ngb_tipo_tancagem',
    'ngb_tipoos',
    'ngb_tipos_bases',
    'ngb_tipos_posicao',
    'ngb_tipos_recipiente',
    'ngb_treinamento',
    'ngb_treinamento_matriz',
    'ngb_usuarios',
    'ngb_usuarios_acesso',
    'ngb_videos',
    'ngb_videos_categoria',
    'ngb_videos_usuario',
    'ngb_videos_usuario_historico',
    'ngb_webcom_defeitos'
];

$generator = new ModelGenerator();
foreach ($tables as $table) {
    $generator->generate($table);
}