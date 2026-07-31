/**
 * JavaScript - Saída e Cobrança v3.0
 * Mobile-first, busca por QR/Placa, cálculo de cobrança, pagamento
 */

document.addEventListener('DOMContentLoaded', function() {
    const buscaInput = document.getElementById('buscaInput');
    const btnBuscar = document.getElementById('btnBuscar');
    const btnLerQR = document.getElementById('btnLerQR');
    const ticketInfo = document.getElementById('ticketInfo');
    const btnConfirmarPagamento = document.getElementById('btnConfirmarPagamento');
    const btnCortesia = document.getElementById('btnCortesia');
    const btnCancelar = document.getElementById('btnCancelar');
    const modalCortesia = new bootstrap.Modal(document.getElementById('modalCortesia'));
    const modalCancelamento = new bootstrap.Modal(document.getElementById('modalCancelamento'));
    
    let ticketAtual = null;

    // Buscar ticket por QR ou Placa
    btnBuscar.addEventListener('click', buscarTicket);
    buscaInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') buscarTicket();
    });

    // Ler QR Code (futuro: usar biblioteca de câmera)
    btnLerQR.addEventListener('click', () => {
        alert('Funcionalidade de leitura por câmera será implementada na próxima sprint.\nPor enquanto, digite o código QR manualmente.');
        buscaInput.focus();
    });

    async function buscarTicket() {
        const valor = buscaInput.value.trim();
        if (!valor) {
            alert('Digite o QR Code ou Placa');
            return;
        }

        try {
            const response = await fetch('../api/v1/tickets.php?acao=buscar&valor=' + encodeURIComponent(valor));
            const data = await response.json();

            if (data.sucesso && data.dados) {
                ticketAtual = data.dados;
                exibirTicket(data.dados);
            } else {
                alert(data.erro || 'Ticket não encontrado');
                ticketInfo.classList.add('d-none');
            }
        } catch (erro) {
            console.error(erro);
            alert('Erro ao buscar ticket');
        }
    }

    function exibirTicket(ticket) {
        document.getElementById('ticketPlaca').textContent = formatarPlaca(ticket.placa_normalizada);
        document.getElementById('ticketModelo').textContent = ticket.modelo_nome || 'Modelo não informado';
        document.getElementById('ticketEntrada').textContent = formatarDataHora(ticket.entrada_utc);
        document.getElementById('ticketTipo').textContent = ticket.tipo_veiculo_nome || 'N/A';
        document.getElementById('ticketMarca').textContent = ticket.marca_nome || 'N/A';
        document.getElementById('ticketCor').textContent = ticket.cor_nome || 'N/A';
        
        // Calcular tempo decorrido
        const entrada = new Date(ticket.entrada_utc);
        const agora = new Date();
        const diffMs = agora - entrada;
        const diffMinutos = Math.floor(diffMs / 60000);
        const horas = Math.floor(diffMinutos / 60);
        const minutos = diffMinutos % 60;
        document.getElementById('tempoDecorrido').textContent = 
            `${horas.toString().padStart(2, '0')}h ${minutos.toString().padStart(2, '0')}min`;

        // Status
        const statusEl = document.getElementById('ticketStatus');
        statusEl.textContent = ticket.status;
        statusEl.className = `badge status-badge ${ticket.status === 'ABERTO' ? 'bg-success' : 'bg-secondary'}`;

        ticketInfo.classList.remove('d-none');
    }

    // Confirmar Pagamento
    btnConfirmarPagamento.addEventListener('click', async () => {
        if (!ticketAtual || ticketAtual.status !== 'ABERTO') {
            alert('Ticket inválido ou já fechado');
            return;
        }

        if (!confirm('Confirmar pagamento e fechar ticket?')) return;

        try {
            const response = await fetch('../api/v1/tickets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'fechar',
                    ticket_id: ticketAtual.id,
                    metodo_pagamento: 'MANUAL'
                })
            });
            const data = await response.json();

            if (data.sucesso) {
                alert('Pagamento confirmado! Ticket fechado com sucesso.');
                ticketInfo.classList.add('d-none');
                buscaInput.value = '';
                ticketAtual = null;
            } else {
                alert(data.erro || 'Erro ao fechar ticket');
            }
        } catch (erro) {
            console.error(erro);
            alert('Erro ao processar pagamento');
        }
    });

    // Aplicar Cortesia
    btnCortesia.addEventListener('click', () => {
        if (!ticketAtual || ticketAtual.status !== 'ABERTO') {
            alert('Ticket inválido ou já fechado');
            return;
        }
        modalCortesia.show();
    });

    document.getElementById('btnAplicarCortesia').addEventListener('click', async () => {
        const motivo = document.getElementById('motivoCortesia').value;
        const obs = document.getElementById('obsCortesia').value;

        if (!motivo) {
            alert('Selecione um motivo para cortesia');
            return;
        }

        try {
            const response = await fetch('../api/v1/tickets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'cortesia',
                    ticket_id: ticketAtual.id,
                    motivo: motivo,
                    observacao: obs
                })
            });
            const data = await response.json();

            if (data.sucesso) {
                alert('Cortesia aplicada com sucesso!');
                modalCortesia.hide();
                ticketInfo.classList.add('d-none');
                buscaInput.value = '';
                ticketAtual = null;
            } else {
                alert(data.erro || 'Erro ao aplicar cortesia');
            }
        } catch (erro) {
            console.error(erro);
            alert('Erro ao aplicar cortesia');
        }
    });

    // Cancelar Ticket
    btnCancelar.addEventListener('click', () => {
        if (!ticketAtual || ticketAtual.status !== 'ABERTO') {
            alert('Ticket inválido ou já fechado');
            return;
        }
        modalCancelamento.show();
    });

    document.getElementById('btnConfirmarCancelamento').addEventListener('click', async () => {
        const justificativa = document.getElementById('justificativaCancelamento').value;

        if (justificativa.length < 20) {
            alert('Justificativa deve ter pelo menos 20 caracteres');
            return;
        }

        if (!confirm('Tem certeza que deseja cancelar este ticket?')) return;

        try {
            const response = await fetch('../api/v1/tickets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'cancelar',
                    ticket_id: ticketAtual.id,
                    justificativa: justificativa
                })
            });
            const data = await response.json();

            if (data.sucesso) {
                alert('Ticket cancelado com sucesso');
                modalCancelamento.hide();
                ticketInfo.classList.add('d-none');
                buscaInput.value = '';
                ticketAtual = null;
            } else {
                alert(data.erro || 'Erro ao cancelar ticket');
            }
        } catch (erro) {
            console.error(erro);
            alert('Erro ao cancelar ticket');
        }
    });

    // Utilitários
    function formatarPlaca(placa) {
        if (!placa || placa.length !== 7) return placa;
        return `${placa.substring(0,3)}${placa.substring(3,4)}${placa.substring(4,7)}`;
    }

    function formatarDataHora(dataIso) {
        if (!dataIso) return '--/--/-- --:--';
        const data = new Date(dataIso);
        return data.toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });
    }
});
