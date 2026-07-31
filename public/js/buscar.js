/**
 * JavaScript - Busca de Tickets v3.0
 * Sistema Estacionamento - Busca por placa ou QR Code
 */

const API_BASE = '../api/v1';
let ticketAtual = null;

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    carregarUsuario();
    
    // Enter nos inputs
    document.getElementById('placa-busca').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') buscarPorPlaca();
    });
    document.getElementById('qr-busca').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') buscarPorQR();
    });
});

/**
 * Carrega informações do usuário logado
 */
function carregarUsuario() {
    fetch(`${API_BASE}/index.php?acao=sessao`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(res => res.json())
    .then(data => {
        if (data.sucesso && data.usuario) {
            document.getElementById('user-info').textContent = 
                `${data.usuario.nome} (${data.usuario.perfil})`;
        } else {
            window.location.href = 'login.php';
        }
    })
    .catch(err => console.error('Erro ao carregar sessão:', err));
}

/**
 * Buscar por placa
 */
function buscarPorPlaca() {
    const placa = document.getElementById('placa-busca').value.trim().toUpperCase();
    
    if (!placa || placa.length < 7) {
        mostrarAlerta('Informe uma placa válida (mínimo 7 caracteres)', 'warning');
        return;
    }
    
    buscarTicket({ tipo: 'placa', valor: placa });
}

/**
 * Buscar por QR Code
 */
function buscarPorQR() {
    const qr = document.getElementById('qr-busca').value.trim();
    
    if (!qr || qr.length < 20) {
        mostrarAlerta('Informe um QR Code válido (ULID com ~26 caracteres)', 'warning');
        return;
    }
    
    buscarTicket({ tipo: 'qr', valor: qr });
}

/**
 * Requisição à API
 */
function buscarTicket(params) {
    mostrarLoading(true);
    esconderResultado();
    
    const url = params.tipo === 'placa' 
        ? `${API_BASE}/tickets.php?placa=${encodeURIComponent(params.valor)}`
        : `${API_BASE}/tickets.php?qr=${encodeURIComponent(params.valor)}`;
    
    fetch(url, {
        method: 'GET',
        credentials: 'include'
    })
    .then(res => res.json())
    .then(data => {
        mostrarLoading(false);
        
        if (data.sucesso && data.ticket) {
            ticketAtual = data.ticket;
            exibirTicket(data.ticket);
        } else {
            mostrarAlerta(data.erro || 'Ticket não encontrado', 'danger');
        }
    })
    .catch(err => {
        mostrarLoading(false);
        mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        console.error('Erro:', err);
    });
}

/**
 * Exibe detalhes do ticket
 */
function exibirTicket(ticket) {
    document.getElementById('resultado').classList.remove('d-none');
    
    // Status badge
    const statusBadge = document.getElementById('status-badge');
    statusBadge.className = 'badge ' + getStatusClass(ticket.status);
    statusBadge.textContent = formatarStatus(ticket.status);
    
    // Preencher dados
    document.getElementById('det-codigo').textContent = ticket.codigo_unico;
    document.getElementById('det-placa').textContent = ticket.placa_normalizada;
    document.getElementById('det-tipo').textContent = ticket.tipo_veiculo || '-';
    document.getElementById('det-marca').textContent = ticket.marca || '-';
    document.getElementById('det-modelo').textContent = ticket.modelo || '-';
    document.getElementById('det-cor').textContent = ticket.cor || '-';
    document.getElementById('det-entrada').textContent = formatarData(ticket.entrada_utc);
    document.getElementById('det-saida').textContent = ticket.saida_utc ? formatarData(ticket.saida_utc) : '-';
    document.getElementById('det-tempo').textContent = ticket.tempo_minutos ? `${ticket.tempo_minutos} min` : '-';
    document.getElementById('det-valor').textContent = ticket.valor_calculado 
        ? formatarMoeda(parseFloat(ticket.valor_calculado)) 
        : '-';
    document.getElementById('det-op-entrada').textContent = ticket.operador_entrada_nome || '-';
    document.getElementById('det-op-saida').textContent = ticket.operador_saida_nome || '-';
    
    // Botões de ação
    exibirAcoes(ticket);
}

/**
 * Exibe botões de ação conforme status e permissão
 */
function exibirAcoes(ticket) {
    const container = document.getElementById('acoes-ticket');
    container.innerHTML = '';
    
    if (ticket.status === 'ABERTO') {
        // Botão ir para saída
        const btnSaida = document.createElement('button');
        btnSaida.className = 'btn btn-success';
        btnSaida.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i>Ir para Saída';
        btnSaida.onclick = () => {
            window.location.href = `saida.php?ticket=${ticket.codigo_unico}`;
        };
        container.appendChild(btnSaida);
        
        // Botão cancelar (se tiver permissão)
        const btnCancelar = document.createElement('button');
        btnCancelar.className = 'btn btn-danger';
        btnCancelar.innerHTML = '<i class="bi bi-x-circle me-1"></i>Cancelar';
        btnCancelar.onclick = () => cancelarTicket();
        container.appendChild(btnCancelar);
    } else if (ticket.status === 'FECHADO') {
        const btnReabrir = document.createElement('button');
        btnReabrir.className = 'btn btn-warning';
        btnReabrir.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i>Solicitar Reabertura';
        btnReabrir.onclick = () => {
            alert('Reabertura requer justificativa e permissão de gestor. Funcionalidade em desenvolvimento.');
        };
        container.appendChild(btnReabrir);
    }
    
    const btnImprimir = document.createElement('button');
    btnImprimir.className = 'btn btn-outline-secondary';
    btnImprimir.innerHTML = '<i class="bi bi-printer me-1"></i>Imprimir';
    btnImprimir.onclick = () => imprimirTicket();
    container.appendChild(btnImprimir);
}

/**
 * Cancelar ticket aberto
 */
function cancelarTicket() {
    if (!confirm('Tem certeza que deseja cancelar este ticket? Esta ação requer justificativa.')) {
        return;
    }
    
    const justificativa = prompt('Informe a justificativa para cancelamento (mínimo 20 caracteres):');
    
    if (!justificativa || justificativa.length < 20) {
        alert('Justificativa deve ter pelo menos 20 caracteres.');
        return;
    }
    
    fetch(`${API_BASE}/tickets.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            acao: 'cancelar',
            ticket_id: ticketAtual.id,
            justificativa: justificativa
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.sucesso) {
            alert('Ticket cancelado com sucesso!');
            buscarPorQR(); // Recarrega
        } else {
            alert('Erro: ' + data.erro);
        }
    })
    .catch(err => {
        alert('Erro na comunicação com o servidor');
        console.error(err);
    });
}

/**
 * Imprimir ticket
 */
function imprimirTicket() {
    if (!ticketAtual) return;
    
    const janela = window.open('', '_blank');
    janela.document.write(`
        <html>
        <head>
            <title>Ticket ${ticketAtual.codigo_unico}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .ticket { border: 2px solid #333; padding: 20px; max-width: 400px; margin: 0 auto; }
                h2 { text-align: center; margin-bottom: 20px; }
                .row { display: flex; justify-content: space-between; margin: 8px 0; }
                .label { font-weight: bold; }
                .qr { text-align: center; margin: 20px 0; font-family: monospace; font-size: 18px; }
            </style>
        </head>
        <body>
            <div class="ticket">
                <h2>COMPROVANTE DE ESTACIONAMENTO</h2>
                <div class="qr">${ticketAtual.codigo_unico}</div>
                <div class="row"><span class="label">Placa:</span><span>${ticketAtual.placa_normalizada}</span></div>
                <div class="row"><span class="label">Tipo:</span><span>${ticketAtual.tipo_veiculo || '-'}</span></div>
                <div class="row"><span class="label">Marca:</span><span>${ticketAtual.marca || '-'}</span></div>
                <div class="row"><span class="label">Modelo:</span><span>${ticketAtual.modelo || '-'}</span></div>
                <div class="row"><span class="label">Cor:</span><span>${ticketAtual.cor || '-'}</span></div>
                <div class="row"><span class="label">Entrada:</span><span>${formatarData(ticketAtual.entrada_utc)}</span></div>
                ${ticketAtual.saida_utc ? `<div class="row"><span class="label">Saída:</span><span>${formatarData(ticketAtual.saida_utc)}</span></div>` : ''}
                ${ticketAtual.tempo_minutos ? `<div class="row"><span class="label">Tempo:</span><span>${ticketAtual.tempo_minutos} min</span></div>` : ''}
                ${ticketAtual.valor_calculado ? `<div class="row"><span class="label">Valor:</span><span>${formatarMoeda(parseFloat(ticketAtual.valor_calculado))}</span></div>` : ''}
                <div class="row"><span class="label">Status:</span><span>${formatarStatus(ticketAtual.status)}</span></div>
                <hr>
                <p style="text-align: center; font-size: 12px; color: #666;">
                    Obrigado por utilizar nossos serviços!
                </p>
            </div>
        </body>
        </html>
    `);
    janela.document.close();
    janela.print();
}

/**
 * Utilitários
 */
function mostrarLoading(show) {
    document.getElementById('loading').classList.toggle('d-none', !show);
}

function esconderResultado() {
    document.getElementById('resultado').classList.add('d-none');
}

function mostrarAlerta(msg, tipo = 'info') {
    const alert = document.getElementById('alert-info');
    const msgSpan = document.getElementById('alert-msg');
    
    alert.className = `alert alert-${tipo} d-flex align-items-center`;
    msgSpan.textContent = msg;
    
    document.getElementById('resultado').classList.remove('d-none');
}

function getStatusClass(status) {
    const map = {
        'ABERTO': 'bg-success',
        'FECHADO': 'bg-danger',
        'CANCELADO': 'bg-secondary'
    };
    return map[status] || 'bg-secondary';
}

function formatarStatus(status) {
    const map = {
        'ABERTO': 'Aberto',
        'FECHADO': 'Fechado',
        'CANCELADO': 'Cancelado'
    };
    return map[status] || status;
}

function formatarData(dataIso) {
    if (!dataIso) return '-';
    const data = new Date(dataIso);
    return data.toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });
}

function formatarMoeda(valor) {
    return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
