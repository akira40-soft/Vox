-- Vox Sistema Eleitoral - Database Schema PostgreSQL
-- Compatible with Render PostgreSQL 18

-- 1. Create ENUM types
CREATE TYPE user_role AS ENUM ('admin', 'organizador', 'candidato', 'eleitor');
CREATE TYPE user_estado AS ENUM ('ativo', 'pendente', 'banido');
CREATE TYPE sala_tipo AS ENUM ('nacional', 'municipal', 'comunitario', 'pesquisa', 'institucional');
CREATE TYPE sala_estado AS ENUM ('rascunho', 'campanha', 'ativa', 'pausada', 'finalizada', 'cancelada');
CREATE TYPE tema_tipo_votacao AS ENUM ('unico', 'multiplo', 'ranking', 'sim_nao');
CREATE TYPE candidato_estado AS ENUM ('ativo', 'removido');
CREATE TYPE vote_sim_nao AS ENUM ('sim', 'nao');
CREATE TYPE campanha_tipo AS ENUM ('proposta', 'comicio', 'debate', 'manifesto');
CREATE TYPE denuncia_estado AS ENUM ('pendente', 'em_analise', 'resolvida', 'rejeitada');
CREATE TYPE convite_estado AS ENUM ('pendente', 'aceite', 'recusado');
CREATE TYPE reacao_tipo AS ENUM ('like', 'heart', 'clap', 'fire', 'adorado', 'hater');

-- 2. Trigger function for ON UPDATE CURRENT_TIMESTAMP behavior
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.atualizado_em = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- 3. Create tables

-- Provinces of Angola
CREATE TABLE provincias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(50) NOT NULL
);

INSERT INTO provincias (nome) VALUES
('Bengo'), ('Benguela'), ('Bié'), ('Cabinda'), ('Cuando Cubango'),
('Cuanza Norte'), ('Cuanza Sul'), ('Cunene'), ('Huambo'), ('Huíla'),
('Luanda'), ('Lunda Norte'), ('Lunda Sul'), ('Malanje'), ('Moxico'),
('Namibe'), ('Uíge'), ('Zaire');

-- Users
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    nome_completo VARCHAR(150) NOT NULL,
    username VARCHAR(50) UNIQUE NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    bilhete_identidade VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    provincia_id INT REFERENCES provincias(id) ON DELETE SET NULL,
    password VARCHAR(255) NOT NULL,
    role user_role DEFAULT 'eleitor',
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    estado user_estado DEFAULT 'pendente',
    token_verificacao VARCHAR(100),
    remember_token VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_estado ON users(estado);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_username ON users(username);

-- Electoral rooms
CREATE TABLE salas_eleitorais (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    codigo_acesso VARCHAR(20) UNIQUE NOT NULL,
    tipo sala_tipo DEFAULT 'institucional',
    provincia_origem INT REFERENCES provincias(id) ON DELETE SET NULL,
    organizador_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    estado sala_estado DEFAULT 'rascunho',
    data_inicio TIMESTAMP,
    data_fim TIMESTAMP,
    data_campanha_inicio TIMESTAMP,
    data_campanha_fim TIMESTAMP,
    data_votacao_inicio TIMESTAMP,
    data_votacao_fim TIMESTAMP,
    fase_atual VARCHAR(50) DEFAULT 'aguardando',
    arquivada_em TIMESTAMP,
    voto_anonimo BOOLEAN DEFAULT TRUE,
    permitir_campanha BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_salas_updated_at BEFORE UPDATE ON salas_eleitorais FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE INDEX idx_salas_codigo ON salas_eleitorais(codigo_acesso);
CREATE INDEX idx_salas_estado ON salas_eleitorais(estado);

-- Themes/topics within a room
CREATE TABLE temas (
    id SERIAL PRIMARY KEY,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    ordem INT DEFAULT 0,
    tipo_votacao tema_tipo_votacao DEFAULT 'unico',
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_temas_sala ON temas(sala_id);

-- Candidates
CREATE TABLE candidatos (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    tema_id INT REFERENCES temas(id) ON DELETE SET NULL,
    nome VARCHAR(150) NOT NULL,
    biografia TEXT,
    foto VARCHAR(255),
    partido VARCHAR(100),
    slogan VARCHAR(200),
    proposta TEXT,
    votos_totais INT DEFAULT 0,
    estado candidato_estado DEFAULT 'ativo',
    criado_por INT REFERENCES users(id) ON DELETE SET NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, sala_id)
);

CREATE INDEX idx_candidatos_sala ON candidatos(sala_id);
CREATE INDEX idx_candidatos_tema ON candidatos(tema_id);

-- Track who entered each room
CREATE TABLE sala_membros (
    id SERIAL PRIMARY KEY,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    papel VARCHAR(50) DEFAULT 'eleitor',
    entrou_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (sala_id, user_id)
);

CREATE INDEX idx_sala_membros_sala ON sala_membros(sala_id);

-- Votes
CREATE TABLE votos (
    id SERIAL PRIMARY KEY,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    tema_id INT REFERENCES temas(id) ON DELETE SET NULL,
    candidato_id INT REFERENCES candidatos(id) ON DELETE SET NULL,
    opcao_sim_nao vote_sim_nao,
    ranking JSON,
    voto_hash VARCHAR(64),
    ip_votante VARCHAR(45),
    user_agent TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (tema_id, user_id, sala_id)
);

CREATE INDEX idx_votos_sala ON votos(sala_id);
CREATE INDEX idx_votos_candidato ON votos(candidato_id);

-- Campaigns
CREATE TABLE campanhas (
    id SERIAL PRIMARY KEY,
    candidato_id INT REFERENCES candidatos(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    titulo VARCHAR(200) NOT NULL,
    conteudo TEXT NOT NULL,
    tipo campanha_tipo DEFAULT 'proposta',
    data_evento TIMESTAMP,
    local VARCHAR(200),
    imagem VARCHAR(255),
    video_url VARCHAR(255),
    curtidas INT DEFAULT 0,
    visualizacoes INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_campanhas_candidato ON campanhas(candidato_id);
CREATE INDEX idx_campanhas_sala ON campanhas(sala_id);

-- Comments
CREATE TABLE comentarios (
    id SERIAL PRIMARY KEY,
    campanha_id INT REFERENCES campanhas(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    parent_id INT REFERENCES comentarios(id) ON DELETE CASCADE,
    conteudo TEXT NOT NULL,
    curtidas INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Campaign Reactions
CREATE TABLE post_reacoes (
    id SERIAL PRIMARY KEY,
    post_id INT NOT NULL REFERENCES campanhas(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tipo reacao_tipo DEFAULT 'like',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, user_id)
);

-- Followers
CREATE TABLE seguidores (
    id SERIAL PRIMARY KEY,
    seguidor_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    seguido_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (seguidor_id, seguido_id)
);

-- Retweets/Shares
CREATE TABLE retweets (
    id SERIAL PRIMARY KEY,
    post_id INT NOT NULL REFERENCES campanhas(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, user_id)
);

-- Direct Messages (DMs)
CREATE TABLE mensagens_diretas (
    id SERIAL PRIMARY KEY,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    remetente_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    destinatario_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    conteudo TEXT NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE notificacoes (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tipo VARCHAR(50),
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notificacoes_user_lida ON notificacoes(user_id, lida);

-- Reports / Complaints
CREATE TABLE denuncias (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    candidato_id INT REFERENCES candidatos(id) ON DELETE CASCADE,
    post_id INT REFERENCES campanhas(id) ON DELETE CASCADE,
    motivo VARCHAR(100) NOT NULL,
    detalhes TEXT,
    estado denuncia_estado DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reports during election
CREATE TABLE reports_eleicao (
    id SERIAL PRIMARY KEY,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    candidato_id INT REFERENCES candidatos(id) ON DELETE SET NULL,
    tipo VARCHAR(50) NOT NULL,
    descricao TEXT NOT NULL,
    estado VARCHAR(50) DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Room invitations
CREATE TABLE convites_sala (
    id SERIAL PRIMARY KEY,
    sala_id INT NOT NULL REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    email_convidado VARCHAR(150),
    username_convidado VARCHAR(50),
    user_id_convidado INT REFERENCES users(id) ON DELETE SET NULL,
    token_convite VARCHAR(100) UNIQUE,
    estado convite_estado DEFAULT 'pendente',
    papel VARCHAR(50) DEFAULT 'candidato',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_convites_token ON convites_sala(token_convite);

-- Audit log
CREATE TABLE auditoria (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    acao VARCHAR(100),
    detalhes TEXT,
    ip VARCHAR(45),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_auditoria_user ON auditoria(user_id);
CREATE INDEX idx_auditoria_criado ON auditoria(criado_em);

-- System statistics
CREATE TABLE estatisticas_sistema (
    id SERIAL PRIMARY KEY,
    metrica VARCHAR(50) UNIQUE NOT NULL,
    valor BIGINT DEFAULT 0,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_estatisticas_updated_at BEFORE UPDATE ON estatisticas_sistema FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

INSERT INTO estatisticas_sistema (metrica) VALUES
('total_eleicoes'), ('total_votos'), ('total_candidatos'), ('total_usuarios');

-- Default admin: email=admin@vox.ao, password=Admin@123
-- The hash below is: password_hash('Admin@123', PASSWORD_DEFAULT)
INSERT INTO users (nome_completo, email, password, role, estado) VALUES
('Administrador Vox', 'admin@vox.ao', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo');
