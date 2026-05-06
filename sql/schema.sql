-- Vox Sistema Eleitoral - Database Schema MySQL
-- Execute: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS vox_db DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
USE vox_db;

-- Provinces of Angola
CREATE TABLE provincias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

INSERT INTO provincias (nome) VALUES
('Bengo'), ('Benguela'), ('Bié'), ('Cabinda'), ('Cuando Cubango'),
('Cuanza Norte'), ('Cuanza Sul'), ('Cunene'), ('Huambo'), ('Huíla'),
('Luanda'), ('Lunda Norte'), ('Lunda Sul'), ('Malanje'), ('Moxico'),
('Namibe'), ('Uíge'), ('Zaire');

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    bilhete_identidade VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    provincia_id INT,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'organizador', 'candidato', 'eleitor') DEFAULT 'eleitor',
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    estado ENUM('ativo', 'pendente', 'banido') DEFAULT 'pendente',
    token_verificacao VARCHAR(100),
    remember_token VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_estado (estado),
    INDEX idx_role (role),
    FOREIGN KEY (provincia_id) REFERENCES provincias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Electoral rooms
CREATE TABLE salas_eleitorais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    codigo_acesso VARCHAR(20) UNIQUE NOT NULL,
    tipo ENUM('nacional', 'municipal', 'comunitario', 'pesquisa', 'institucional') DEFAULT 'institucional',
    provincia_origem INT,
    organizador_id INT NOT NULL,
    estado ENUM('rascunho', 'campanha', 'ativa', 'pausada', 'finalizada', 'cancelada') DEFAULT 'rascunho',
    data_inicio DATETIME,
    data_fim DATETIME,
    voto_anonimo TINYINT(1) DEFAULT 1,
    permitir_campanha TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo_acesso),
    INDEX idx_estado (estado),
    FOREIGN KEY (organizador_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provincia_origem) REFERENCES provincias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Themes/topics within a room
CREATE TABLE temas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    ordem INT DEFAULT 0,
    tipo_votacao ENUM('unico', 'multiplo', 'ranking', 'sim_nao') DEFAULT 'unico',
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    INDEX idx_sala (sala_id)
) ENGINE=InnoDB;

-- Candidates
CREATE TABLE candidatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    sala_id INT NOT NULL,
    tema_id INT,
    nome VARCHAR(150) NOT NULL,
    biografia TEXT,
    foto VARCHAR(255),
    partido VARCHAR(100),
    slogan VARCHAR(200),
    proposta TEXT,
    votos_totais INT DEFAULT 0,
    estado ENUM('ativo', 'removido') DEFAULT 'ativo',
    criado_por INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    FOREIGN KEY (tema_id) REFERENCES temas(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sala (sala_id),
    INDEX idx_tema (tema_id)
) ENGINE=InnoDB;

-- Votes
CREATE TABLE votos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    user_id INT,
    tema_id INT,
    candidato_id INT,
    opcao_sim_nao ENUM('sim', 'nao'),
    ranking JSON,
    voto_hash VARCHAR(64),
    ip_votante VARCHAR(45),
    user_agent TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (tema_id) REFERENCES temas(id) ON DELETE SET NULL,
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE SET NULL,
    UNIQUE KEY unique_vote (tema_id, user_id, sala_id),
    INDEX idx_sala (sala_id),
    INDEX idx_candidato (candidato_id)
) ENGINE=InnoDB;

-- Campaigns
CREATE TABLE campanhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidato_id INT NOT NULL,
    sala_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    conteudo TEXT NOT NULL,
    tipo ENUM('proposta', 'comicio', 'debate', 'manifesto') DEFAULT 'proposta',
    data_evento DATETIME,
    local VARCHAR(200),
    imagem VARCHAR(255),
    video_url VARCHAR(255),
    curtidas INT DEFAULT 0,
    visualizacoes INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    INDEX idx_candidato (candidato_id),
    INDEX idx_sala (sala_id)
) ENGINE=InnoDB;

-- Comments
CREATE TABLE comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campanha_id INT,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    conteudo TEXT NOT NULL,
    curtidas INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campanha_id) REFERENCES campanhas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comentarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Campaign Reactions
CREATE TABLE post_reacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    tipo ENUM('like', 'heart', 'clap', 'fire') DEFAULT 'like',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES campanhas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reacao (post_id, user_id)
) ENGINE=InnoDB;

-- Followers
CREATE TABLE seguidores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seguidor_id INT NOT NULL,
    seguido_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (seguidor_id, seguido_id),
    FOREIGN KEY (seguidor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seguido_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Retweets/Shares
CREATE TABLE retweets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_retweet (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES campanhas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Direct Messages (DMs)
CREATE TABLE mensagens_diretas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    remetente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    conteudo TEXT NOT NULL,
    lida TINYINT(1) DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    FOREIGN KEY (remetente_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications
CREATE TABLE notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tipo VARCHAR(50),
    mensagem TEXT NOT NULL,
    lida TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_lida (user_id, lida)
) ENGINE=InnoDB;

-- Reports / Complaints
CREATE TABLE denuncias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    candidato_id INT,
    post_id INT,
    motivo VARCHAR(100) NOT NULL,
    detalhes TEXT,
    estado ENUM('pendente', 'em_analise', 'resolvida', 'rejeitada') DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES campanhas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Room invitations
CREATE TABLE convites_sala (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    email_convidado VARCHAR(150),
    user_id_convidado INT,
    token_convite VARCHAR(100) UNIQUE,
    estado ENUM('pendente', 'aceite', 'recusado') DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_convidado) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_token (token_convite)
) ENGINE=InnoDB;

-- Audit log
CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    acao VARCHAR(100),
    detalhes TEXT,
    ip VARCHAR(45),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB;

-- System statistics
CREATE TABLE estatisticas_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metrica VARCHAR(50) UNIQUE NOT NULL,
    valor BIGINT DEFAULT 0,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO estatisticas_sistema (metrica) VALUES
('total_eleicoes'), ('total_votos'), ('total_candidatos'), ('total_usuarios');

-- Default admin: email=admin@vox.ao, password=Admin@123
-- The hash below is: password_hash('Admin@123', PASSWORD_DEFAULT)
-- Precomputed: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC
INSERT INTO users (nome_completo, email, password, role, estado) VALUES
('Administrador Vox', 'admin@vox.ao', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo');
