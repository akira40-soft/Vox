# Vox - Plataforma Eleitoral Angolana

## 📋 Visão Geral

Sistema de gestão e votação eletrônica para eleições nacionais, municipais, comunitárias e pesquisas em Angola.

**Versão:** 1.0.0  
**Status:** Em Desenvolvimento  
**Última Atualização:** 9 de Abril de 2026  

---

## 🏗️ Arquitetura do Projeto

```
Projeto-elitoral/
├── config/              # Configuração central
│   ├── db.php          # Conexão com banco de dados
│   ├── helpers.php     # Funções auxiliares
│   └── constants.php   # Constantes globais
│
├── api/                # Endpoints RESTful JSON
│   ├── notifications.php  # Sistema de notificações
│   └── results.php        # Resultados eleitorais
│
├── assets/             # Recursos estáticos
│   ├── js/main.js      # JavaScript principal
│   ├── css/            # Estilos CSS
│   └── images/         # Imagens e ícones
│
├── includes/           # Templates reutilizáveis
│   ├── header.php      # Cabeçalho autenticado
│   └── footer.php      # Rodapé
│
├── sql/                # Banco de dados
│   └── schema.sql      # Estrutura das tabelas
│
├── uploads/            # Diretório para uploads (user)
│
└── *.php               # Páginas principais
```

---

## 👥 Tipos de Utilizadores

### 1. **Administrador** (`admin`)
- Acesso total ao sistema
- Aprovar/rejeitar contas de organizadores
- Visualizar estatísticas globais
- Gerenciar usuários e salas

### 2. **Organizador** (`organizador`)
- Criar e gerenciar salas eleitorais
- Adicionar temas e candidatos
- Visualizar resultados em tempo real
- Enviar convites para participantes
- **Estado inicial:** Pendente (requer aprovação do admin)

### 3. **Candidato** (`candidato`)
- Participar em eleições como candidato
- Ver seus votos em tempo real
- Criar campanhas
- **Estado inicial:** Ativo (acesso imediato)

### 4. **Eleitor** (`eleitor`)
- Votar em salas ativas
- Ver resultados públicos
- Consultar campanhas dos candidatos
- **Estado inicial:** Ativo (acesso imediato)

---

## 🔐 Segurança

### Autenticação
- ✅ Hash de senha com `PASSWORD_DEFAULT` (bcrypt)
- ✅ Sessões seguras com regeneração de ID
- ✅ Cookie "Lembrar-me" com token único
- ✅ Auto-logout após inatividade

### Autorização
- ✅ Verificação de role em páginas sensíveis
- ✅ Validação de propriedade antes de editar
- ✅ CSRF tokens em formulários
- ✅ Controle de acesso baseado em papéis (RBAC)

### Proteção de Dados
- ✅ Prepared statements em todas as queries
- ✅ Sanitização de inputs com `htmlspecialchars()`
- ✅ Logging de auditoria de ações críticas
- ✅ Hash dos votos para rastreamento

---

## 📊 Funcionalidades Principais

### Sistema de Votação
- [ ] Votação de resposta única (um candidato)
- [ ] Votação múltipla (vários candidatos)
- [ ] Votação sim/não (plebiscitos)
- [ ] Ranking de preferências
- [ ] Voto anônimo ou identificado

### Salas Eleitorais
- [x] Criar salas em rascunho
- [x] Publicar salas como ativas
- [x] Pausar votações
- [x] Finalizar e arquivar resultados
- [x] Gerar código de acesso único

### Resultados
- [x] Contagem em tempo real
- [x] Gráficos de resultados
- [x] Exportação em CSV
- [x] Histórico de votações
- [x] Análise por região (província)

### Notificações
- [x] Sistema de notificações em tempo real
- [x] Alertas para novas votações
- [x] Confirmação de votos
- [x] Notificação de resultados

---

## 🗄️ Banco de Dados

### Tabelas Principais

| Tabela | Descrição | Registros |
|--------|-----------|-----------|
| `users` | Utilizadores do sistema | 2+ |
| `salas_eleitorais` | Salas de votação | 0+ |
| `temas` | Tópicos/questões | 0+ |
| `candidatos` | Candidatos | 0+ |
| `votos` | Registros de votação | 0+ |
| `campanhas` | Campanhas de candidatos | 0+ |
| `notificacoes` | Notificações dos usuários | 0+ |
| `auditoria` | Log de ações | 10+ |

**Password do Admin Padrão:**
- Email: `admin@vox.ao`
- Senha: `Admin@123`

---

## 🛠️ Instalação & Setup

### Pré-requisitos
- PHP 8.0+
- MySQL/MariaDB 10.1+
- Servidor web (Apache/Nginx)

### Passos

1. **Clonar repositório**
```bash
cd Projeto-elitoral
```

2. **Configurar banco de dados**
```bash
mysql -u root -p < sql/schema.sql
```

3. **Ajustar config/db.php**
```php
$host = 'localhost';
$db = 'vox_db';
$user = 'root';
$pass = 'sua_senha';
```

4. **Iniciar servidor PHP**
```bash
php -S localhost:8080 -t D:\...\Projeto-elitoral
```

5. **Acessar no browser**
```
http://localhost:8080/login.php
```

---

## 📱 Estados & Transições

### Usuário
```
ativo ← → pendente → ativo
          ↓
        banido
```

### Sala
```
rascunho → ativa → pausada → finalizada
                                ↓
                            cancelada
```

---

## ⚠️ Problemas Conhecidos & Melhorias

### Críticos (Resolvidos ✅)
- [x] Inconsistência de nomes de coluna (u.nome → u.nome_completo)
- [x] SQL Injection em votar.php
- [x] Campos desabilitados impedindo envio de dados
- [x] Validação fraca em registro

### Em Desenvolvimento 🔄
- [ ] Gráficos de resultados em tempo real
- [ ] Exportação de dados avançada
- [ ] Sistema de relatórios
- [ ] Integração com SMS/Email
- [ ] App mobile
- [ ] Análise preditiva com IA

### Backlog 📋
- [ ] Tema dark mode
- [ ] Autenticação de dois fatores
- [ ] Backup automático
- [ ] Migração de dados de outros sistemas
- [ ] API pública para integrações

---

## 🤝 Contribuindo

1. Fork o projeto
2. Create sua branch (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Open uma Pull Request

---

## 📞 Contacto & Suporte

**Email:** suporte@vox.ao  
**WhatsApp:** +244 922 XX XXXX  
**Website:** www.vox.ao  

---

## 📄 Licença

Este projeto é propriedade de [Seu Nome/Organização]. Todos os direitos reservados.

---

## 🎯 Roadmap 2026

- **Q1:** Sistema de votação básico ✅
- **Q2:** Gráficos e relatórios
- **Q3:** App mobile e API pública
- **Q4:** IA e análise preditiva

---

**Ultima revisão:** 9 de Abril de 2026  
**Desenvolvedor:** Isaac Quarenta  
**Versão da Documentação:** 1.0
