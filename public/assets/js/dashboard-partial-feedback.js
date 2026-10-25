(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var statusRegion = document.getElementById('dashboard-async-status');

        function isDashboardTarget(target) {
            return target instanceof Element &&
                (target.matches('.dashboard-content') || target.id === 'management-summary');
        }

        function getTarget(evt) {
            var target = evt.detail && evt.detail.target;
            return isDashboardTarget(target) ? target : null;
        }

        function announce(message) {
            if (statusRegion) statusRegion.textContent = message;
        }

        function targetLabel(target) {
            if (target.id === 'leads-dashboard-content') return 'Análise de Leads';
            if (target.id === 'properties-dashboard-content') return 'Análise de Imóveis';
            if (target.id === 'owners-dashboard-content') return 'Análise de Proprietários';
            return 'Resumo gerencial';
        }

        function retryRequest(evt) {
            var source = evt.detail && (evt.detail.elt || evt.detail.triggeringEvent && evt.detail.triggeringEvent.target);
            if (source instanceof Element && source.isConnected) {
                if (source instanceof HTMLFormElement) {
                    source.requestSubmit();
                    return;
                }
                if (source.matches('a, button, input[type="submit"]')) {
                    source.click();
                    return;
                }
            }

            window.location.reload();
        }

        function showFailure(evt, message, reloadPage) {
            var target = getTarget(evt);
            if (!target) return;

            target.removeAttribute('aria-busy');
            var existing = target.querySelector('.dashboard-request-error');
            if (existing) existing.remove();

            var notice = document.createElement('div');
            notice.className = 'dashboard-inline-error dashboard-request-error';
            notice.setAttribute('role', 'alert');

            var text = document.createElement('p');
            text.textContent = message;
            notice.appendChild(text);

            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'outline secondary';
            retry.textContent = reloadPage ? 'Recarregar página' : 'Tentar novamente';
            retry.addEventListener('click', function () {
                notice.remove();
                if (reloadPage) {
                    window.location.reload();
                } else {
                    retryRequest(evt);
                }
            });
            notice.appendChild(retry);
            target.prepend(notice);
        }

        document.body.addEventListener('htmx:beforeRequest', function (evt) {
            var target = getTarget(evt);
            if (!target) return;

            target.setAttribute('aria-busy', 'true');
            var priorFailure = target.querySelector('.dashboard-request-error');
            if (priorFailure) priorFailure.remove();
        });

        document.body.addEventListener('htmx:afterSettle', function (evt) {
            var target = getTarget(evt);
            if (!target) return;

            target.removeAttribute('aria-busy');
            announce(targetLabel(target) + ' atualizada.');
        });

        document.body.addEventListener('htmx:responseError', function (evt) {
            var status = evt.detail && evt.detail.xhr ? evt.detail.xhr.status : 0;
            var message = status === 401 || status === 403
                ? 'Sua sessão não permite atualizar este conteúdo. Recarregue a página para entrar novamente.'
                : status === 429
                    ? 'Muitas atualizações em sequência. Aguarde um pouco e tente novamente.'
                    : 'Não foi possível atualizar este bloco. Verifique a conexão e tente novamente.';
            showFailure(evt, message, status === 401 || status === 403);
        });

        document.body.addEventListener('htmx:sendError', function (evt) {
            showFailure(evt, 'Falha de comunicação com o servidor. Verifique sua conexão e tente novamente.');
        });

        document.body.addEventListener('htmx:timeout', function (evt) {
            showFailure(evt, 'A atualização demorou demais. Tente novamente.');
        });

        document.body.addEventListener('htmx:swapError', function (evt) {
            showFailure(evt, 'A resposta chegou, mas não pôde ser exibida. Tente novamente.');
        });

        document.querySelector('main').addEventListener('keydown', function (evt) {
            if (evt.key !== ' ' || !(evt.target instanceof Element)) return;
            var link = evt.target.closest('a[role="button"]');
            if (!link || !evt.currentTarget.contains(link)) return;
            evt.preventDefault();
            link.click();
        });
    });
}());
