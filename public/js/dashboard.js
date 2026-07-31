/**
 * JavaScript - Dashboard v3.0
 * Sistema Estacionamento - Estatísticas e Gráficos
 */

const API_BASE = '../api/v1';
let chartHoraInstance = null;
let chartTipoInstance = null;

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    carregarUsuario();
    carregarDashboard();
    
    // Auto-refresh a cada 60 segundos
    setInterval(carregarDashboard, 60000);
});

/**
 * Carrega informações do usuário
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
    .catch(err => {
        console.error('Erro ao carregar sessão:', err);
        window.location.href = 'login.php';
    });
}

/**
 * Carrega todos os dados do dashboard
 */
function carregarDashboard() {
    Promise.all([
        fetch(`${API_BASE}/dashboard.php?tipo=estatisticas`, { credentials: 'include' }),
        fetch(`${API_BASE}/dashboard.php?tipo=movimento-hora`, { credentials: 'include' }),
        fetch(`${API_BASE}/dashboard.php?tipo=tipo-veiculo`, { credentials: 'include' }),
        fetch(`${API_BASE}/dashboard.php?tipo=marcas-ranking`, { credentials: 'include' }),
        fetch(`${API_BASE}/dashboard.php?tipo=ultimos-tickets`, { credentials: 'include' })
    ])
    .then(([res1, res2, res3, res4, res5]) => {
        return Promise.all([res1.json(), res2.json(), res3.json(), res4.json(), res5.json()]);
    })
    .then(([stats, movimento, tipos, marcas, tickets]) => {
        if (stats.sucesso) atualizarCards(stats.dados);
        if (movimento.sucesso) atualizarChartHora(movimento.dados);
        if (tipos.sucesso) atualizarChartTipo(tipos.dados);
        if (marcas.sucesso) atualizarRankingMarcas(marcas.dados);
        if (tickets.sucesso) atualizarTabelaTickets(tickets.dados);
    })
    .catch(err => {
        console.error('Erro ao carregar dashboard:', err);
    });
}

/**
 * Atualiza cards de estatísticas
 */
function atualizarCards(dados) {
    document.getElementById('stat-ocupados').textContent = dados.veiculos_patio || 0;
    document.getElementById('stat-receita').textContent = formatarMoeda(dados.receita_hoje || 0);
    document.getElementById('stat-tickets').textContent = dados.tickets_fechados_hoje || 0;
    document.getElementById('stat-medio').textContent = formatarMoeda(dados.ticket_medio || 0);
}

/**
 * Atualiza gráfico de movimento por hora
 */
function atualizarChartHora(dados) {
    const ctx = document.getElementById('chartHora').getContext('2d');
    
    const labels = dados.map(d => `${d.hora.toString().padStart(2, '0')}:00`);
    const entradas = dados.map(d => d.entradas);
    const saidas = dados.map(d => d.saidas);
    
    if (chartHoraInstance) {
        chartHoraInstance.destroy();
    }
    
    chartHoraInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Entradas',
                    data: entradas,
                    backgroundColor: 'rgba(37, 99, 235, 0.8)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Saídas',
                    data: saidas,
                    backgroundColor: 'rgba(22, 163, 74, 0.8)',
                    borderColor: 'rgba(22, 163, 74, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

/**
 * Atualiza gráfico de tipos de veículo
 */
function atualizarChartTipo(dados) {
    const ctx = document.getElementById('chartTipo').getContext('2d');
    
    const labels = dados.map(d => d.tipo);
    const valores = dados.map(d => d.quantidade);
    const cores = [
        'rgba(37, 99, 235, 0.8)',
        'rgba(22, 163, 74, 0.8)',
        'rgba(234, 179, 8, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(168, 85, 247, 0.8)'
    ];
    
    if (chartTipoInstance) {
        chartTipoInstance.destroy();
    }
    
    chartTipoInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: valores,
                backgroundColor: cores.slice(0, labels.length),
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
}

/**
 * Atualiza ranking de marcas
 */
function atualizarRankingMarcas(dados) {
    const tbody = document.getElementById('tabela-marcas');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">Sem dados</td></tr>';
        return;
    }
    
    const total = dados.reduce((sum, m) => sum + m.quantidade, 0);
    
    tbody.innerHTML = dados.map((marca, index) => `
        <tr>
            <td>${index + 1}</td>
            <td><strong>${marca.marca}</strong></td>
            <td class="text-end">${marca.quantidade}</td>
            <td class="text-end">${((marca.quantidade / total) * 100).toFixed(1)}%</td>
        </tr>
    `).join('');
}

/**
 * Atualiza tabela de últimos tickets
 */
function atualizarTabelaTickets(dados) {
    const tbody = document.getElementById('tabela-tickets');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Sem tickets</td></tr>';
        return;
    }
    
    tbody.innerHTML = dados.map(ticket => {
        const statusClass = ticket.status === 'ABERTO' ? 'bg-success' : 
                           ticket.status === 'FECHADO' ? 'bg-danger' : 'bg-secondary';
        
        return `
            <tr>
                <td><strong>${ticket.placa_normalizada}</strong></td>
                <td><code>${ticket.codigo_unico.substring(0, 12)}...</code></td>
                <td>${ticket.tipo_veiculo || '-'}</td>
                <td>${ticket.marca || ''} ${ticket.modelo || ''}</td>
                <td>${formatarData(ticket.entrada_utc)}</td>
                <td><span class="badge ${statusClass}">${ticket.status}</span></td>
                <td>
                    ${ticket.status === 'ABERTO' 
                        ? `<a href="saida.php?ticket=${ticket.codigo_unico}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-in-right"></i></a>`
                        : `<a href="buscar.php?ticket=${ticket.codigo_unico}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>`
                    }
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Utilitários
 */
function formatarMoeda(valor) {
    return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatarData(dataIso) {
    if (!dataIso) return '-';
    const data = new Date(dataIso);
    return data.toLocaleString('pt-BR', { 
        timeZone: 'America/Sao_Paulo',
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit'
    });
}
