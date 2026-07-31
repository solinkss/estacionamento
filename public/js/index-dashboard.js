/**
 * JavaScript - Dashboard Operador (index.php)
 * Sistema Estacionamento v3.0
 * Versão simplificada para página inicial
 */

const API_BASE = '../api/v1';

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    carregarEstatisticas();
    carregarUltimosTickets();
    
    // Auto-refresh a cada 60 segundos
    setInterval(carregarEstatisticas, 60000);
});

/**
 * Carrega estatísticas dos cards
 */
function carregarEstatisticas() {
    fetch(`${API_BASE}/dashboard.php?tipo=estatisticas`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
        if (data.sucesso && data.dados) {
            document.getElementById('stat-abertos').textContent = data.dados.veiculos_patio || 0;
            document.getElementById('stat-receita').textContent = formatarMoeda(data.dados.receita_hoje || 0);
            document.getElementById('stat-media').textContent = '- min'; // Implementação futura
            document.getElementById('stat-ocupacao').textContent = '-%'; // Implementação futura
        }
    })
    .catch(err => console.error('Erro ao carregar estatísticas:', err));
}

/**
 * Carrega últimos tickets
 */
function carregarUltimosTickets() {
    fetch(`${API_BASE}/dashboard.php?tipo=ultimos-tickets`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
        const tbody = document.getElementById('tabela-tickets');
        
        if (!data.sucesso || !data.dados || data.dados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Sem movimentações recentes</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.dados.slice(0, 10).map(ticket => {
            const statusClass = ticket.status === 'ABERTO' ? 'badge-aberto' : 'badge-fechado';
            const statusTexto = ticket.status === 'ABERTO' ? 'Aberto' : 'Fechado';
            
            return `
                <tr>
                    <td><strong>${ticket.placa_normalizada}</strong></td>
                    <td>${ticket.tipo_veiculo || '-'}</td>
                    <td>${formatarData(ticket.entrada_utc)}</td>
                    <td><span class="badge-status ${statusClass}">${statusTexto}</span></td>
                    <td>
                        ${ticket.status === 'ABERTO' 
                            ? `<a href="saida.php?ticket=${ticket.codigo_unico}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-in-right"></i></a>`
                            : `<a href="buscar.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>`
                        }
                    </td>
                </tr>
            `;
        }).join('');
    })
    .catch(err => {
        console.error('Erro ao carregar tickets:', err);
        document.getElementById('tabela-tickets').innerHTML = 
            '<tr><td colspan="5" class="text-center py-4 text-danger">Erro ao carregar dados</td></tr>';
    });
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
