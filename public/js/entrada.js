/**
 * JavaScript - Tela de Entrada
 * Sistema Estacionamento v3.0
 * Autocomplete para marca/modelo/cor com suporte a PENDENTE/ATIVO
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-entrada');
    const placaInput = document.getElementById('placa');
    
    // Configura inputs de autocomplete
    setupAutocomplete('marca', 'marca-suggestions', 'marcas_veiculo');
    setupAutocomplete('modelo', 'modelo-suggestions', 'modelos_veiculo');
    setupAutocomplete('cor', 'cor-suggestions', 'cores_veiculo');
    
    // Máscara de placa (Mercosul)
    placaInput.addEventListener('input', function(e) {
        let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        
        // Formatação visual: ABC1D23
        if (value.length >= 4) {
            value = value.substring(0, 3) + value.substring(3);
        }
        
        e.target.value = value.substring(0, 7);
    });
    
    // Submit do formulário
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const dados = Object.fromEntries(formData.entries());
        
        // Validações
        if (!dados.placa || dados.placa.length !== 7) {
            alert('Placa inválida. Digite 7 caracteres alfanuméricos.');
            return;
        }
        
        if (!dados.tipo_veiculo_id) {
            alert('Selecione o tipo de veículo.');
            return;
        }
        
        try {
            const response = await fetch('../api/v1/tickets.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'criar_entrada',
                    placa: dados.placa,
                    tipo_veiculo_id: dados.tipo_veiculo_id,
                    marca: dados.marca || null,
                    marca_id: dados.marca_id || null,
                    modelo: dados.modelo || null,
                    modelo_id: dados.modelo_id || null,
                    cor: dados.cor || null,
                    cor_id: dados.cor_id || null,
                    observacao: dados.observacao || null
                })
            });
            
            const resultado = await response.json();
            
            if (response.ok && resultado.success) {
                mostrarSucesso(resultado.data);
                form.reset();
            } else {
                alert('Erro: ' + (resultado.error || 'Operação não autorizada'));
            }
        } catch (error) {
            console.error('Erro ao criar entrada:', error);
            alert('Erro de conexão. Tente novamente.');
        }
    });
    
    /**
     * Configura autocomplete para um campo
     */
    function setupAutocomplete(campoId, suggestionsId, tabela) {
        const input = document.getElementById(campoId);
        const suggestionsDiv = document.getElementById(suggestionsId);
        let debounceTimer;
        
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();
            
            if (query.length < 2) {
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            debounceTimer = setTimeout(() => {
                buscarSugestoes(query, tabela, suggestionsDiv);
            }, 300);
        });
        
        // Fecha ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target !== input) {
                suggestionsDiv.style.display = 'none';
            }
        });
        
        // Seleção via teclado
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const primeiroItem = suggestionsDiv.querySelector('.autocomplete-item');
                if (primeiroItem) {
                    primeiroItem.click();
                }
            }
        });
    }
    
    /**
     * Busca sugestões na API
     */
    async function buscarSugestoes(query, tabela, suggestionsDiv) {
        try {
            const response = await fetch(`../api/v1/catalogo.php?tabela=${tabela}&q=${encodeURIComponent(query)}`);
            const resultados = await response.json();
            
            if (resultados.length === 0) {
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            suggestionsDiv.innerHTML = '';
            
            resultados.forEach(item => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                
                const statusClass = item.status === 'ATIVO' ? 'status-ativo' : 'status-pendente';
                const statusLabel = item.status === 'ATIVO' ? '✓' : '⏳';
                
                div.innerHTML = `
                    ${item.nome}
                    <span class="status-badge ${statusClass}">${statusLabel} ${item.status}</span>
                `;
                
                div.addEventListener('click', function() {
                    const input = document.getElementById(tabela.replace('_veiculo', ''));
                    const hiddenInput = document.getElementById(tabela.replace('_veiculo', '') + '_id');
                    
                    input.value = item.nome;
                    hiddenInput.value = item.id;
                    suggestionsDiv.style.display = 'none';
                    
                    // Se for modelo, atualiza dependência da marca
                    if (tabela === 'modelos_veiculo' && item.marca_id) {
                        document.getElementById('marca_id').value = item.marca_id;
                    }
                });
                
                suggestionsDiv.appendChild(div);
            });
            
            suggestionsDiv.style.display = 'block';
        } catch (error) {
            console.error('Erro ao buscar sugestões:', error);
        }
    }
    
    /**
     * Mostra modal de sucesso com QR Code
     */
    function mostrarSucesso(dados) {
        document.getElementById('sucesso-placa').textContent = dados.placa;
        document.getElementById('sucesso-codigo').textContent = dados.codigo_unico;
        document.getElementById('sucesso-entrada').textContent = formatarData(dados.entrada_utc);
        
        // Gera QR Code (usando API pública ou biblioteca local)
        const qrContainer = document.getElementById('qr-code-container');
        qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(dados.codigo_unico)}" alt="QR Code" class="img-fluid">`;
        
        const modal = new bootstrap.Modal(document.getElementById('modal-sucesso'));
        modal.show();
    }
    
    /**
     * Formata data UTC para horário local
     */
    function formatarData(dataUtc) {
        const date = new Date(dataUtc + 'Z');
        return date.toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });
    }
});
