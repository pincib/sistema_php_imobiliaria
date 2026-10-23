<?php

declare(strict_types=1);

namespace App\View;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Security\Csrf;
use RuntimeException;

final class Renderer
{
    private string $viewsPath;

    public function __construct(?string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views';
    }

    /**
     * Renderiza uma view com dados e layout.
     *
     * @param string $template Nome do template (ex: 'auth/login', 'dashboard/index')
     * @param array<string, mixed> $data Dados passados para o template
     * @param string|null|false $layout Layout a envolver a view (padrão: 'layouts/main', ou false para nenhum)
     */
    public function render(string $template, array $data = [], string|null|false $layout = 'layouts/main'): string
    {
        $templateFile = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template) . '.php';

        if (!file_exists($templateFile)) {
            throw new RuntimeException("Arquivo de view não encontrado: {$templateFile}");
        }

        // Variáveis globais úteis para todas as views
        $globalData = [
            'currentUser' => AuthService::user(),
            'csrfToken' => Csrf::getToken(),
            'csrfField' => Csrf::field(),
            'flashes' => SessionManager::getFlashes(),
            'e' => function (?string $val): string {
                return htmlspecialchars($val ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            'formatDate' => [\App\Support\DateHelper::class, 'format'],
            'formatOnlyDate' => [\App\Support\DateHelper::class, 'formatDate'],
            'formatTime' => [\App\Support\DateHelper::class, 'formatTime'],
            'humanDiff' => [\App\Support\DateHelper::class, 'humanDiff'],
            'getFollowUpStatus' => [\App\Support\DateHelper::class, 'getFollowUpStatus'],
            'getStatusLabel' => [\App\Support\DateHelper::class, 'getStatusLabel'],
            'getStatusBadgeClass' => [\App\Support\DateHelper::class, 'getStatusBadgeClass'],
            'getInteractionLabel' => [\App\Modules\Leads\Services\InteractionService::class, 'getTypeLabel'],
            'getInteractionBadgeClass' => [\App\Modules\Leads\Services\InteractionService::class, 'getTypeBadgeClass'],
            'interactionTypes' => \App\Modules\Leads\Services\InteractionService::ALLOWED_TYPES,
        ];

        $viewData = array_merge($globalData, $data);

        // Renderiza conteúdo interno
        $content = $this->renderFile($templateFile, $viewData);

        // Se layout foi desativado (false), retorna conteúdo direto
        if ($layout === false) {
            return $content;
        }

        // Renderiza com layout
        $layoutName = $layout ?? 'layouts/main';
        $layoutFile = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $layoutName) . '.php';

        if (!file_exists($layoutFile)) {
            throw new RuntimeException("Arquivo de layout não encontrado: {$layoutFile}");
        }

        $viewData['content'] = $content;
        return $this->renderFile($layoutFile, $viewData);
    }

    /**
     * Isola o escopo de variáveis na inclusão do arquivo PHP.
     *
     * @param array<string, mixed> $variables
     */
    private function renderFile(string $file, array $variables): string
    {
        extract($variables, EXTR_SKIP);

        ob_start();
        try {
            require $file;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
