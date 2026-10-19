<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stockki General Application Language Lines (Portuguese)
    |--------------------------------------------------------------------------
    */

    'app_name' => 'Stockki',
    'tagline' => 'Controle Inteligente Multitenant de Estoque e Validades',

    // Landing Page
    'landing' => [
        'badge' => 'Controle de Estoque e Validades de Nova Geração',
        'hero_title' => 'Desperdício Zero de Validade. Controle Total do Estoque.',
        'hero_subtitle' => 'Automatize o controle de lotes, monitore prazos críticos de validade em tempo real e leia rótulos de embalagens com reconhecimento óptico instantâneo.',
        'cta_start' => 'Começar Gratuitamente',
        'cta_demo' => 'Demonstração Interativa',
        'cta_login' => 'Acessar Painel',
        'features_title' => 'Projetado para Confiabilidade Absoluta no Estoque',
        'features_subtitle' => 'Elimine perdas e descartes por vencimento com monitoramento automático, leitura óptica e segurança multitenant.',
        'feat_expiry_title' => 'Análise de Validade em Tempo Real',
        'feat_expiry_desc' => 'Cálculo automatizado categorizando produtos como regulares, a vencer em menos de 30 dias ou vencidos.',
        'feat_ocr_title' => 'Scanner Óptico de Rótulos',
        'feat_ocr_desc' => 'Utilize a câmera do aparelho ou envie fotos de embalagens para extrair SKU, lote e validade em segundos.',
        'feat_rbac_title' => 'Permissões Granulares por Cargo',
        'feat_rbac_desc' => 'Defina papéis e acessos sob medida para administradores, gerentes de estoque e operadores.',
        'feat_tenant_title' => 'Isolamento de Dados Multitenant',
        'feat_tenant_desc' => 'Segurança de nível corporativo garantindo sigilo absoluto entre workspaces sem risco de vazamento.',
    ],

    // Auth (Login / Register)
    'auth' => [
        'login_title' => 'Acesse seu espaço de trabalho',
        'login_subtitle' => 'Informe suas credenciais para gerenciar seu estoque e alertas de validade',
        'register_title' => 'Crie seu espaço de trabalho',
        'register_subtitle' => 'Comece hoje a monitorar seu estoque e eliminar perdas por vencimento',
        'email' => 'Endereço de E-mail',
        'password' => 'Senha',
        'confirm_password' => 'Confirmar Senha',
        'workspace_name' => 'Nome da Empresa / Espaço',
        'full_name' => 'Nome Completo',
        'remember_me' => 'Lembrar minha sessão',
        'forgot_password' => 'Esqueceu a senha?',
        'sign_in' => 'Entrar no Painel',
        'create_account' => 'Criar Espaço',
        'demo_fill' => 'Credenciais de Demonstração',
        'have_account' => 'Já possui um espaço? Entrar',
        'no_account' => 'Ainda não possui conta? Cadastre-se',
        'showcase_badge' => 'Pronto para Produção',
        'showcase_title' => 'Otimização Inteligente de Vida Útil',
        'showcase_desc' => 'Junte-se a centros logísticos, farmácias e varejos de alimentos que mantêm estoques com zero perdas.',
    ],

    // Navigation & Layout
    'nav' => [
        'dashboard' => 'Painel Principal',
        'inventory' => 'Estoque',
        'expiry_alerts' => 'Alertas de Validade',
        'recognition' => 'Reconhecimento por Imagem',
        'users' => 'Usuários e Permissões',
        'batches' => 'Lotes e Séries',
        'reports' => 'Relatórios',
        'settings' => 'Configurações',
        'logout' => 'Sair',
        'profile' => 'Perfil do Espaço',
    ],

    // Product Attributes & Labels
    'product' => [
        'singular' => 'Produto',
        'plural' => 'Produtos',
        'name' => 'Nome do Produto',
        'sku' => 'SKU / Código',
        'batch_number' => 'Número do Lote',
        'quantity' => 'Quantidade Atual',
        'manufacture_date' => 'Data de Fabricação',
        'expiry_date' => 'Data de Validade',
        'safety_stock' => 'Estoque Mínimo de Segurança',
        'status' => 'Status',
        'image' => 'Foto do Produto',
        'extraction_metadata' => 'Metadados de Extração OCR',
        'actions' => 'Ações',
    ],

    // User Management & RBAC
    'user' => [
        'singular' => 'Usuário',
        'plural' => 'Usuários',
        'title' => 'Gestão de Usuários e Permissões',
        'subtitle' => 'Gerencie os membros da equipe, cargos e permissões granulares de acesso',
        'name' => 'Nome Completo',
        'email' => 'Endereço de E-mail',
        'role' => 'Cargo Atribuído',
        'status' => 'Status',
        'permissions' => 'Permissões de Acesso',
        'password' => 'Senha',
        'password_hint' => 'Deixe em branco para manter a senha atual ao editar',
        'active' => 'Ativo',
        'inactive' => 'Desativado',
        'activate' => 'Ativar',
        'deactivate' => 'Desativar',
        'role_admin' => 'Administrador',
        'role_manager' => 'Gerente de Estoque',
        'role_operator' => 'Operador de Estoque',
        'perm_inventory' => 'Gerenciar Estoque (CRUD)',
        'perm_users' => 'Gerenciar Usuários e Permissões',
        'perm_reports' => 'Visualizar e Exportar Relatórios',
        'perm_scan' => 'Escanear Produtos e OCR',
    ],

    // Expiry Status Indicators
    'expiry' => [
        'status_expired' => 'Vencido',
        'status_expiring_soon' => 'Vence em menos de 30 dias',
        'status_safe' => 'Regular / Seguro',
        'days_remaining' => ':count dias restantes',
        'expired_ago' => 'Vencido há :count dias',
        'expires_today' => 'Vence Hoje',
        'alert_banner' => 'Você possui :count produtos necessitando de atenção imediata.',
    ],

    // Automated Product Recognition (OCR / Vision / Camera)
    'recognition' => [
        'title' => 'Mecanismo de Reconhecimento de Produtos',
        'subtitle' => 'Capture o rótulo usando a câmera do aparelho ou envie um arquivo de imagem',
        'upload_label' => 'Carregue ou capture uma foto do rótulo',
        'scan_button' => 'Analisar Rótulo',
        'analyzing' => 'Processando informações do rótulo...',
        'confidence' => 'Confiança da Extração',
        'auto_fill' => 'Aplicar Dados Extraídos',
        're_scan' => 'Escanear Novamente',
        'camera_mode' => 'Câmera ao Vivo',
        'upload_mode' => 'Envio de Arquivo',
        'start_camera' => 'Abrir Câmera',
        'stop_camera' => 'Fechar Câmera',
        'capture_photo' => 'Capturar Foto',
        'camera_notice' => 'Alinhe o rótulo do produto dentro do quadro de leitura',
    ],

    // Common Actions & Buttons
    'actions' => [
        'create' => 'Cadastrar Novo Produto',
        'edit' => 'Editar',
        'delete' => 'Excluir',
        'save' => 'Salvar Alterações',
        'update' => 'Atualizar Alterações',
        'cancel' => 'Cancelar',
        'search' => 'Buscar por nome, SKU ou lote...',
        'filter' => 'Filtrar',
        'export' => 'Exportar CSV',
        'confirm_delete' => 'Tem certeza de que deseja remover este produto?',
        'confirm_delete_user' => 'Tem certeza de que deseja excluir este usuário?',
        'new_user' => 'Novo Usuário',
        'edit_user' => 'Editar Usuário',
    ],

    // Flash Messages & Feedback
    'messages' => [
        'product_created' => 'Produto cadastrado com sucesso no estoque.',
        'product_updated' => 'Dados do produto atualizados com sucesso.',
        'product_deleted' => 'Produto removido dos registros de estoque.',
        'user_created' => 'Conta de usuário criada e permissões atribuídas com sucesso.',
        'user_updated' => 'Dados do usuário atualizados com sucesso.',
        'user_deleted' => 'Conta de usuário excluída permanentemente.',
        'user_status_toggled' => 'Status do usuário alterado com sucesso.',
        'login_success' => 'Bem-vindo de volta ao seu espaço de trabalho.',
        'register_success' => 'Espaço de trabalho criado com sucesso.',
    ],
];
