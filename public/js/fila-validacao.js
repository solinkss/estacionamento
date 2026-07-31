/**
 * JavaScript - Fila de Validação
 * Sistema Estacionamento v3.0
 * Gerenciamento de cadastros PENDENTE/ATIVO
 */

document.addEventListener('DOMContentLoaded', function() {
    carregarCadastrosPendentes();
    
    // Setup dos tabs
    const tabs = document.querySelectorAll('#tabs-cadastros .nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('data-bs-target').replace('#tab-', 'lista-');
            carregarListaPorTipo(target.replace('lista-', ''));
        });
    });
});

/**
 * Carrega contadores de pendentes por tipo
 */
async function carregarCadastrosPendentes() {
    try {
        const tipos = ['marcas', 'modelos', 'cores'];
        
        for (const tipo of tipos) {
            const tabela = tipo === 'marcas' ? 'marcas_veiculo' : 
                          tipo === 'modelos' ? 'modelos_veiculo' : 'cores_veiculo';
            
            const response = await fetch(`../api/v1/admin-catalogo.php?tabela=${tabela}&status=PENDENTE`);
            const dados = await response.json();
            
            const count = dados.length || 0;
            document.getElementById(`count-${tipo}`).textContent = count;
        }
        
        // Carrega lista inicial (marcas)
        carregarListaPorTipo('marcas');
    } catch (error) {
        console.error('Erro ao carregar pendentes:', error);
    }
}

/**
 * Carrega lista de um tipo específico
 */
async function carregarListaPorTipo(tipo) {
    const tabela = tipo === 'marcas' ? 'marcas_veiculo' : 
                  tipo === 'modelos' ? 'modelos_veiculo' : 'cores_veiculo';
    
    const container = document.getElementById(`lista-${tipo}`);
    container.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div></div>';
    
    try {
        const response = await fetch(`../api/v1/admin-catalogo.php?tabela=${tabela}&status=PENDENTE&incluir_ativos=true`);
        const dados = await response.json();
        
        if (dados.length === 0) {
            container.innerHTML = '<div class="col-12 text-center py-4 text-muted">Nenhum cadastro encontrado</div>';
            return;
        }
        
        container.innerHTML = '';
        
        dados.forEach(item => {
            const card = criarCardCadastro(item, tipo, tabela);
            container.appendChild(card);
        });
    } catch (error) {
        console.error('Erro ao carregar lista:', error);
        container.innerHTML = '<div class="col-12 text-center py-4 text-danger">Erro ao carregar dados</div>';
    }
}

/**
 * Cria card visual para um item de cadastro
 */
function criarCardCadastro(item, tipo, tabela) {
    const col = document.createElement('div');
    col.className = 'col-12 col-md-6 col-lg-4';
    
    const statusClass = item.status === 'ATIVO' ? 'bg-success' : 'bg-warning';
    const statusLabel = item.status === 'ATIVO' ? '✓ ATIVO' : '⏳ PENDENTE';
    
    let infoExtra = '';
    if (tipo === 'modelos' && item.marca_nome) {
        infoExtra = `<small class="text-muted d-block">Marca: ${item.marca_nome}</small>`;
    }
    
    if (item.criado_por_ticket_id) {
        infoExtra += `<small class="text-muted d-block">Origem: Ticket ${item.criado_por_ticket_id.substring(0, 8)}</small>`;
    }
    
    const botoes = item.status === 'PENDENTE' ? `
        <div class="btn-group w-100" role="group">
            <button class="btn btn-success btn-action" onclick="aprovarCadastro('${tabela}', '${item.id}')">
                <i class="bi bi-check-lg"></i> Aprovar
            </button>
            <button class="btn btn-warning btn-action" onclick="abrirModalMesclar('${tabela}', '${item.id}', '${item.nome}')">
                <i class="bi bi-diagram-3"></i> Mesclar
            </button>
        </div>
    ` : `
        <span class="text-muted small"><i>Cadastro ativo</i></span>
    `;
    
    col.innerHTML = `
        <div class="card card-cadastro shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0">${item.nome}</h6>
                    <span class="badge ${statusClass}">${statusLabel}</span>
                </div>
                ${infoExtra}
                <hr class="my-2">
                <small class="text-muted d-block">Criado em: ${formatarData(item.created_at)}</small>
                <div class="mt-3">
                    ${botoes}
                </div>
            </div>
        </div>
    `;
    
    return col;
}

/**
 * Aprova cadastro PENDENTE → ATIVO
 */
async function aprovarCadastro(tabela, id) {
    if (!confirm('Deseja aprovar este cadastro como ATIVO?')) {
        return;
    }
    
    try {
        const response = await fetch('../api/v1/admin-catalogo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'aprovar',
                tabela: tabela,
                id: id
            })
        });
        
        const resultado = await response.json();
        
        if (resultado.success) {
            alert('Cadastro aprovado com sucesso!');
            carregarCadastrosPendentes();
            carregarListaPorTipo(tabela.replace('_veiculo', ''));
        } else {
            alert('Erro: ' + resultado.error);
        }
    } catch (error) {
        console.error('Erro ao aprovar:', error);
        alert('Erro de conexão');
    }
}

/**
 * Abre modal de mesclagem
 */
let itemParaMesclar = null;

async function abrirModalMesclar(tabela, id, nome) {
    itemParaMesclar = { tabela, id, nome };
    
    document.getElementById('mesclar-nome-pendente').textContent = nome;
    
    // Busca cadastros ATIVOS similares para mesclagem
    try {
        const response = await fetch(`../api/v1/admin-catalogo.php?tabela=${tabela}&status=ATIVO`);
        const ativos = await response.json();
        
        const select = document.getElementById('mesclar-selecionar');
        select.innerHTML = '';
        
        ativos.forEach(ativo => {
            if (ativo.id !== id) {
                const option = document.createElement('option');
                option.value = ativo.id;
                option.textContent = ativo.nome;
                select.appendChild(option);
            }
        });
        
        const modal = new bootstrap.Modal(document.getElementById('modal-mesclar'));
        modal.show();
    } catch (error) {
        console.error('Erro ao buscar ativos:', error);
        alert('Erro ao carregar cadastros ativos');
    }
}

// Confirmação da mesclagem
document.getElementById('btn-confirmar-mesclar')?.addEventListener('click', async function() {
    if (!itemParaMesclar) return;
    
    const idAtivo = document.getElementById('mesclar-selecionar').value;
    
    if (!idAtivo) {
        alert('Selecione um cadastro ativo para mesclar');
        return;
    }
    
    try {
        const response = await fetch('../api/v1/admin-catalogo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mesclar',
                tabela: itemParaMesclar.tabela,
                id_pendente: itemParaMesclar.id,
                id_ativo: idAtivo
            })
        });
        
        const resultado = await response.json();
        
        if (resultado.success) {
            alert('Mesclagem realizada com sucesso! Tickets atualizados.');
            
            // Fecha modal
            const modalEl = document.getElementById('modal-mesclar');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
            
            // Recarrega listas
            carregarCadastrosPendentes();
            const tipo = itemParaMesclar.tabela.replace('_veiculo', '');
            carregarListaPorTipo(tipo);
            
            itemParaMesclar = null;
        } else {
            alert('Erro: ' + resultado.error);
        }
    } catch (error) {
        console.error('Erro ao mesclar:', error);
        alert('Erro de conexão');
    }
});

/**
 * Formata data
 */
function formatarData(dataIso) {
    const date = new Date(dataIso);
    return date.toLocaleString('pt-BR');
}
