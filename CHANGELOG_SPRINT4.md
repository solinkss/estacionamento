# Changelog - Sistema Estacionamento v3.0

## Sprint 4 - Saída e Cobrança (Completo)

### Novos Arquivos Criados

#### Frontend
- `public/saida.php` - Tela de saída e cobrança mobile-first
  - Busca por QR Code ou placa
  - Exibição de tempo decorrido em tempo real
  - Cálculo automático de valor
  - Botões: Confirmar Pagamento, Cortesia, Cancelar
  - Modais para cortesia e cancelamento
  - Navegação responsiva Bootstrap 5.3

- `public/js/saida.js` - JavaScript da tela de saída
  - Busca assíncrona de tickets
  - Formatação de dados (placa, data/hora)
  - Integração com APIs de fechar, cortesia, cancelar
  - Validações de formulário

#### Backend API
- `api/v1/tickets.php` (Atualizado)
  - Nova ação `buscar` - busca por QR ou placa via POST
  - Nova ação `fechar` - confirma pagamento MANUAL
  - Nova ação `cortesia` - aplica cortesia com motivo
  - Nova ação `cancelar` - cancela ticket com justificativa (min 20 chars)
  - Respostas padronizadas em português: `{sucesso, dados, erro}`
  - Transações com rollback em caso de erro
  - Auditoria integrada em todas as operações

#### Core
- `src/Core/ParkingCore.php` (Atualizado)
  - Método `buscarTicketPorQR(string $qrCode)` - busca específica por ULID
  - Método `buscarTicketPorPlaca(string $placa)` - busca último ticket ABERTO
  - Refatoração do método `buscarTicket()` para usar métodos específicos
  - Campos retornados padronizados: `tipo_veiculo_nome`, `marca_nome`, `modelo_nome`, `cor_nome`

### Funcionalidades Implementadas

#### 1. Busca de Tickets
- ✅ Busca por QR Code (ULID 26 caracteres)
- ✅ Busca por placa normalizada (Mercosul)
- ✅ Retorna último ticket ABERTO para placa
- ✅ Dados completos: veículo, marca, modelo, cor, operador

#### 2. Fechamento com Pagamento
- ✅ Cálculo de tempo em minutos (UTC)
- ✅ Busca tarifa vigente por tipo_veiculo
- ✅ Motor de cobrança determinístico (5 modos)
- ✅ Atualização transacional do ticket
- ✅ Auditoria SAIDA_FECHADA com valor
- ✅ Status: FECHADO + PAGO

#### 3. Cortesia
- ✅ Motivo obrigatório (select)
- ✅ Observação opcional
- ✅ Valor zerado automaticamente
- ✅ Status: CORTESIA
- ✅ Auditoria com motivo detalhado

#### 4. Cancelamento
- ✅ Justificativa obrigatória (mínimo 20 caracteres)
- ✅ Confirmação prévia
- ✅ Status: CANCELADO
- ✅ Auditoria TICKET_CANCELADO

### Segurança e Conformidade

#### RBAC Aplicado
- `TICKET_CRIAR_ENTRADA` - criar entrada
- `TICKET_FECHAR` - fechar/cortesia
- `TICKET_CANCELAR` - cancelar (operador não tem, apenas admin/gestor)
- Mensagens genéricas de erro: "Operação não autorizada"

#### Auditoria WORM
- Todos os eventos registrados com hash encadeado
- Contexto JSON com detalhes da operação
- Timestamp UTC ISO8601
- IP do terminal capturado

#### Concorrência
- Lock `FOR UPDATE` em operações de fechamento
- Constraint `placa_aberta UNIQUE` previne duplicidade
- Transações com rollback automático

### Matriz de Status das Ações

| Ação | Permissão | Método | Endpoint | Status |
|------|-----------|--------|----------|--------|
| Buscar ticket | Todos | GET/POST | `/api/v1/tickets.php` | ✅ Pronto |
| Fechar (pagamento) | Operador+ | POST | `/api/v1/tickets.php` | ✅ Pronto |
| Aplicar cortesia | Operador+ | POST | `/api/v1/tickets.php` | ✅ Pronto |
| Cancelar ticket | Admin/Gestor | POST | `/api/v1/tickets.php` | ✅ Pronto |

### Próximos Passos (Sprint 5)

#### Pendentes
- [ ] Dashboard gestor (ocupação, receita, ticket médio)
- [ ] Fila de validação (aprovar/mesclar cadastros PENDENTE)
- [ ] Relatórios gerenciais (ranking marcas, modelos similares SOUNDEX)
- [ ] Export auditoria CSV/PDF assinado
- [ ] Anonimização LGPD (clientes >12 meses sem interação)
- [ ] Reabertura de ticket (gestor com justificativa)

#### Melhorias Futuras
- [ ] Leitura QR Code por câmera (biblioteca JS)
- [ ] Webhook gateway PIX/cartão
- [ ] Rate limiting implementado (Redis/fallback arquivo)
- [ ] SSL/TLS para produção
- [ ] PWA com offline básico

### Testes Recomendados

#### Unitário
```bash
php tests/BillingEngineTest.php
php tests/ParkingCoreTest.php
php tests/AuthDomainTest.php
```

#### Integração (Postman/Insomnia)
1. Criar entrada → validar ULID gerado
2. Buscar por placa → retornar ticket ABERTO
3. Fechar ticket → validar cálculo e auditoria
4. Tentar duplicidade → erro constraint DB
5. Cancelar com <20 chars → erro validação

#### Carga (k6/JMeter)
- 10 terminais simultâneos
- 100 entradas/saídas
- Validar zero perda de dados

### Checklist Definition of Done

- [x] Tela saída mobile-first Bootstrap 5.3
- [x] Busca QR/placa funcional
- [x] Cálculo cobrança determinístico
- [x] Pagamento MANUAL registrado
- [x] Cortesia com motivo auditado
- [x] Cancelamento com justificativa
- [x] APIs REST documentadas
- [x] Auditoria WORM integrada
- [x] RBAC aplicado nas ações
- [ ] Testes unitários (pendente)
- [ ] Testes carga (pendente)
- [ ] Guia implantação atualizado

---

**Status Sprint 4:** ✅ COMPLETO  
**Próxima Sprint:** 5 - Admin e LGPD  
**Data:** Julho 2026
