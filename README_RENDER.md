# Como Fazer Deploy no Render (Vox Electoral)

Este projeto foi configurado para ser compatível com o **Render** usando **Docker** e **PostgreSQL**.

## 1. Configuração do Banco de Dados (PostgreSQL no Render)
1. Crie uma nova instância de **PostgreSQL** no dashboard do Render.
2. Copie a **Internal Database URL** (ou External, se for conectar de fora).
3. O projeto está configurado para ler automaticamente a variável de ambiente `DATABASE_URL`.

### Importar o Schema
Para importar as tabelas no seu banco PostgreSQL do Render:
1. No dashboard do Render, vá até a sua base de dados `vox_db`.
2. Use o botão **Connect** -> **PSQL Command** para abrir um terminal (ou use uma ferramenta como DBeaver/pgAdmin com a External URL).
3. Execute o conteúdo do arquivo `sql/schema_pgsql.sql`.
   - *Nota: Se usar o terminal do Render, você pode copiar e colar o conteúdo do SQL.*

## 2. Deploy da Aplicação
1. No Render, clique em **New +** -> **Web Service**.
2. Conecte o seu repositório GitHub.
3. No campo **Runtime**, selecione **Docker**.
4. Certifique-se de que o **Build Context** aponta para a pasta onde está o `Dockerfile`.
5. Em **Environment Variables**, adicione:
   - `DATABASE_URL`: (A URL do seu banco Postgres)
   - `APP_ENV`: `production`

## 3. Alterações Realizadas para Compatibilidade
- **Dockerfile**: Criado para instalar as extensões `pdo_pgsql` e configurar o Apache.
- **config/db.php**: Atualizado para detectar o driver `pgsql` e parsear a `DATABASE_URL`.
- **sql/schema_pgsql.sql**: Versão convertida do banco de dados para PostgreSQL 18 (com tipos ENUM e triggers).
- **Consultas SQL**: Corrigidas instruções `INSERT IGNORE` para `ON CONFLICT DO NOTHING`.

## 4. Projeto Local
**Atenção**: As alterações foram feitas apenas dentro da pasta `Vox`. O projeto local (`Projeto-elitoral`) permanece inalterado e usando MySQL.
