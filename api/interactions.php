<?php
/**
 * api/interactions.php - Social Hub Backend Handler
 * Handles posts, comments, and reactions via AJAX/fetch.
 */

// BLINDAGEM DE OUTPUT: Prevenir que Notices quebrem o JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once '../config/helpers.php';

$response = ['success' => false, 'message' => 'Ação inválida.'];

try {
    // Ensure user is logged in
    $userId = requireAuth();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // CSRF validation for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($token)) {
            throw new Exception('Token de segurança inválido ou sessão expirada.');
        }
    }

    switch ($action) {
        case 'create_post':
            $salaId = (int)($_POST['sala_id'] ?? 0);
            $conteudo = sanitize($_POST['conteudo'] ?? '');
            
            if (empty($conteudo)) throw new Exception("O conteúdo não pode estar vazio.");
            
            // Validate if user is a candidate
            $stmt = $pdo->prepare("SELECT id FROM candidatos WHERE user_id = ? AND sala_id = ?");
            $stmt->execute([$userId, $salaId]);
            $cand = $stmt->fetch();
            $candId = $cand ? $cand['id'] : null;

            $imageName = null;
            $videoName = null;
            $audioName = null;
            
            $targetDir = "../uploads/campaign/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // Image
            if (!empty($_FILES['imagem']['name'])) {
                $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (in_array($ext, $allowed)) {
                    $imageName = "img_" . time() . "_" . uniqid() . "." . $ext;
                    move_uploaded_file($_FILES['imagem']['tmp_name'], $targetDir . $imageName);
                }
            }

            // Video
            if (!empty($_FILES['video']['name'])) {
                $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
                $allowed = ['mp4','webm','ogg','mov'];
                if (in_array($ext, $allowed)) {
                    $videoName = "vid_" . time() . "_" . uniqid() . "." . $ext;
                    move_uploaded_file($_FILES['video']['tmp_name'], $targetDir . $videoName);
                }
            }

            // Audio
            if (!empty($_FILES['audio']['name'])) {
                $ext = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
                $allowed = ['mp3','wav','ogg','m4a'];
                if (in_array($ext, $allowed)) {
                    $audioName = "aud_" . time() . "_" . uniqid() . "." . $ext;
                    move_uploaded_file($_FILES['audio']['tmp_name'], $targetDir . $audioName);
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO campanhas (candidato_id, user_id, sala_id, titulo, conteudo, imagem, video_url, audio_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $titulo = substr($conteudo, 0, 50);
            $stmt->execute([$candId, $userId, $salaId, $titulo, $conteudo, $imageName, $videoName, $audioName]);
            
            $response = ['success' => true, 'message' => 'Campanha publicada com sucesso!'];
            break;

        case 'retweet':
            $postId = (int)($_POST['post_id'] ?? 0);
            
            // Check if already retweeted
            $stmt = $pdo->prepare("SELECT id FROM retweets WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("DELETE FROM retweets WHERE id = ?");
                $stmt->execute([$existing['id']]);
                $response = ['success' => true, 'action' => 'removed'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO retweets (post_id, user_id) VALUES (?, ?)");
                $stmt->execute([$postId, $userId]);
                
                // Notify post author
                $stmt = $pdo->prepare("SELECT user_id, sala_id FROM campanhas WHERE id = ?");
                $stmt->execute([$postId]);
                $post = $stmt->fetch();
                if ($post && $post['user_id'] != $userId) {
                    notifyUser($post['user_id'], 'retweet', $_SESSION['user_nome'] . " retweetou a sua proposta.", "sala_detalhes.php?id=" . $post['sala_id']);
                }
                
                $response = ['success' => true, 'action' => 'added'];
            }
            break;

        case 'toggle_follow':
            $targetId = (int)($_POST['target_id'] ?? 0);
            if ($targetId == $userId) throw new Exception("Não pode seguir-se a si mesmo.");
            
            $stmt = $pdo->prepare("SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
            $stmt->execute([$userId, $targetId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("DELETE FROM seguidores WHERE id = ?");
                $stmt->execute([$existing['id']]);
                $response = ['success' => true, 'action' => 'unfollowed'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO seguidores (seguidor_id, seguido_id) VALUES (?, ?)");
                $stmt->execute([$userId, $targetId]);
                
                // Notify followed user
                notifyUser($targetId, 'seguidor', $_SESSION['user_nome'] . " começou a seguir você.", "perfil.php?id=" . $userId);
                
                $response = ['success' => true, 'action' => 'followed'];
            }
            break;

        case 'delete_room':
            $sid = (int)($_POST['sala_id'] ?? 0);

            // Permission check: Admin or Organizer
            $stmt = $pdo->prepare("SELECT organizador_id FROM salas_eleitorais WHERE id = ?");
            $stmt->execute([$sid]);
            $orgId = $stmt->fetchColumn();

            if (!$orgId) throw new Exception("Sala não encontrada.");

            if ($orgId != $userId && $_SESSION['user_role'] !== 'admin') {
                throw new Exception("Não tem permissão para eliminar esta sala.");
            }

            // Perform cascade-style deletion inside a transaction
            $pdo->beginTransaction();
            try {
                // Get campaign IDs
                $stmt = $pdo->prepare("SELECT id FROM campanhas WHERE sala_id = ?");
                $stmt->execute([$sid]);
                $campaignIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($campaignIds)) {
                    $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
                    $pdo->prepare("DELETE FROM post_reacoes WHERE post_id IN ($placeholders)")->execute($campaignIds);
                    $pdo->prepare("DELETE FROM comentarios WHERE campanha_id IN ($placeholders)")->execute($campaignIds);
                    $pdo->prepare("DELETE FROM retweets WHERE post_id IN ($placeholders)")->execute($campaignIds);
                }

                $pdo->prepare("DELETE FROM campanhas WHERE sala_id = ?")->execute([$sid]);
                $pdo->prepare("DELETE FROM votos WHERE sala_id = ?")->execute([$sid]);

                // Get candidate IDs
                $stmt = $pdo->prepare("SELECT id FROM candidatos WHERE sala_id = ?");
                $stmt->execute([$sid]);
                $candIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($candIds)) {
                    $placeholders = implode(',', array_fill(0, count($candIds), '?'));
                    $pdo->prepare("DELETE FROM denuncias WHERE candidato_id IN ($placeholders)")->execute($candIds);
                }
                if (!empty($campaignIds)) {
                    $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
                    $pdo->prepare("DELETE FROM denuncias WHERE post_id IN ($placeholders)")->execute($campaignIds);
                }

                $pdo->prepare("DELETE FROM candidatos WHERE sala_id = ?")->execute([$sid]);
                $pdo->prepare("DELETE FROM temas WHERE sala_id = ?")->execute([$sid]);
                $pdo->prepare("DELETE FROM convites_sala WHERE sala_id = ?")->execute([$sid]);
                $pdo->prepare("DELETE FROM sala_membros WHERE sala_id = ?")->execute([$sid]);
                $pdo->prepare("DELETE FROM mensagens_diretas WHERE sala_id = ?")->execute([$sid]);

                $pdo->prepare("DELETE FROM salas_eleitorais WHERE id = ?")->execute([$sid]);

                $pdo->commit();
                auditLog($userId, 'apagar_sala', "Sala eliminada via Hub: id=$sid");

                $response = ['success' => true, 'message' => 'Sala eliminada com sucesso.'];
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'add_comment':
            $postId = (int)($_POST['post_id'] ?? 0);
            $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $conteudo = sanitize($_POST['conteudo'] ?? '');
            
            if (empty($conteudo)) throw new Exception("O comentário não pode estar vazio.");
            
            $stmt = $pdo->prepare("INSERT INTO comentarios (campanha_id, user_id, parent_id, conteudo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$postId, $userId, $parentId, $conteudo]);
            
            // Notify post author
            $stmt = $pdo->prepare("SELECT user_id, sala_id FROM campanhas WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();
            if ($post && $post['user_id'] != $userId) {
                notifyUser($post['user_id'], 'comentario', $_SESSION['user_nome'] . " comentou na sua proposta.", "sala_detalhes.php?id=" . $post['sala_id']);
            }
            
            $response = ['success' => true, 'message' => 'Comentário adicionado!'];
            break;

        case 'add_reaction':
        case 'react': // Merged old and new endpoint names
            $postId = (int)($_POST['post_id'] ?? 0);
            $tipo = sanitize($_POST['tipo'] ?? 'adorado');
            if (!in_array($tipo, ['adorado', 'hater', 'like'])) throw new Exception("Tipo de reação inválida.");
            if ($tipo === 'like') $tipo = 'adorado'; // Normalize
            
            // Toggle reaction
            $stmt = $pdo->prepare("SELECT id, tipo FROM post_reacoes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                if ($existing['tipo'] === $tipo) {
                    $stmt = $pdo->prepare("DELETE FROM post_reacoes WHERE id = ?");
                    $stmt->execute([$existing['id']]);
                    $response = ['success' => true, 'action' => 'removed'];
                } else {
                    $stmt = $pdo->prepare("UPDATE post_reacoes SET tipo = ? WHERE id = ?");
                    $stmt->execute([$tipo, $existing['id']]);
                    $response = ['success' => true, 'action' => 'changed'];
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO post_reacoes (post_id, user_id, tipo) VALUES (?, ?, ?)");
                $stmt->execute([$postId, $userId, $tipo]);
                
                if ($tipo === 'adorado') {
                    $stmt = $pdo->prepare("SELECT user_id, sala_id FROM campanhas WHERE id = ?");
                    $stmt->execute([$postId]);
                    $post = $stmt->fetch();
                    if ($post && $post['user_id'] != $userId) {
                        notifyUser($post['user_id'], 'heart', $_SESSION['user_nome'] . " adorou a sua publicação!", "sala_detalhes.php?id=" . $post['sala_id']);
                    }
                }
                $response = ['success' => true, 'action' => 'added'];
            }
            break;
            
        case 'update_candidate':
            $candId = (int)($_POST['candidate_id'] ?? 0);
            $nome = sanitize($_POST['nome'] ?? '');
            $partido = sanitize($_POST['partido'] ?? '');
            $slogan = sanitize($_POST['slogan'] ?? '');
            
            $stmt = $pdo->prepare("SELECT id FROM candidatos WHERE id = ? AND user_id = ?");
            $stmt->execute([$candId, $userId]);
            if (!$stmt->fetch()) throw new Exception("Permissão negada.");
            
            $foto = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $newName = "cand_" . time() . "_" . $candId . "." . $ext;
                $targetDir = "../uploads/candidatos/";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                move_uploaded_file($_FILES['foto']['tmp_name'], $targetDir . $newName);
                $foto = $newName;
            }
            
            if ($foto) {
                $stmt = $pdo->prepare("UPDATE candidatos SET nome = ?, partido = ?, slogan = ?, foto = ? WHERE id = ?");
                $stmt->execute([$nome, $partido, $slogan, $foto, $candId]);
            } else {
                $stmt = $pdo->prepare("UPDATE candidatos SET nome = ?, partido = ?, slogan = ? WHERE id = ?");
                $stmt->execute([$nome, $partido, $slogan, $candId]);
            }
            
            $response = ['success' => true, 'message' => 'Perfil de candidato atualizado!'];
            break;

        case 'report':
            $targetType = sanitize($_POST['target_type'] ?? 'candidato');
            $targetId = (int)($_POST['target_id'] ?? 0);
            $motivo = sanitize($_POST['motivo'] ?? 'Outro');
            $detalhes = sanitize($_POST['detalhes'] ?? '');
            
            $stmt = $pdo->prepare("
                INSERT INTO denuncias (user_id, candidato_id, post_id, motivo, detalhes)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $candId = ($targetType === 'candidato') ? $targetId : null;
            $postId = ($targetType === 'post') ? $targetId : null;
            
            $stmt->execute([$userId, $candId, $postId, $motivo, $detalhes]);
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('organizador', 'admin')");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $label = ($targetType === 'candidato') ? 'Candidato' : 'Publicação';
            $msg = "Nova Denúncia: $label foi reportado por '$motivo'.";
            $salaLink = "sala_detalhes.php";
            if ($postId) {
                $sStmt = $pdo->prepare("SELECT sala_id FROM campanhas WHERE id = ?");
                $sStmt->execute([$postId]);
                $sId = $sStmt->fetchColumn();
                if ($sId) $salaLink .= "?id=" . $sId . "#social-tab-reports";
            }
            
            foreach ($admins as $adminId) {
                notifyUser($adminId, 'denuncia', $msg, $salaLink);
            }
            
            $response = ['success' => true, 'message' => 'Denúncia enviada aos organizadores.'];
            break;

        case 'list_reports':
            $salaId = (int)($_GET['sala_id'] ?? 0);
            $sStmt = $pdo->prepare("SELECT organizador_id FROM salas_eleitorais WHERE id = ?");
            $sStmt->execute([$salaId]);
            $orgId = $sStmt->fetchColumn();
            
            if ($orgId != $userId && $_SESSION['user_role'] !== 'admin') {
                throw new Exception("Acesso negado.");
            }
            
            $stmt = $pdo->prepare("
                SELECT d.*, u.nome_completo as autor_nome,
                       COALESCE(c.nome, 'Publicação') as target_name
                FROM denuncias d
                JOIN users u ON d.user_id = u.id
                LEFT JOIN candidatos c ON d.candidato_id = c.id
                WHERE (d.candidato_id IN (SELECT id FROM candidatos WHERE sala_id = ?) 
                   OR d.post_id IN (SELECT id FROM campanhas WHERE sala_id = ?))
                ORDER BY d.criado_em DESC
            ");
            $stmt->execute([$salaId, $salaId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = ['success' => true, 'reports' => $reports];
            break;

        case 'resolve_report':
            $reportId = (int)($_POST['report_id'] ?? 0);
            $novoEstado = sanitize($_POST['estado'] ?? 'resolvido');
            
            $stmt = $pdo->prepare("
                SELECT s.organizador_id 
                FROM denuncias d
                LEFT JOIN candidatos c ON d.candidato_id = c.id
                LEFT JOIN campanhas cp ON d.post_id = cp.id
                LEFT JOIN salas_eleitorais s ON (c.sala_id = s.id OR cp.sala_id = s.id)
                WHERE d.id = ?
            ");
            $stmt->execute([$reportId]);
            $orgId = $stmt->fetchColumn();
            
            if ($orgId != $userId && $_SESSION['user_role'] !== 'admin') {
                throw new Exception("Acesso negado.");
            }
            
            $pdo->prepare("UPDATE denuncias SET estado = ? WHERE id = ?")->execute([$novoEstado, $reportId]);
            $response = ['success' => true];
            break;

        case 'fetch_comments':
            $postId = (int)($_GET['post_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT c.*, u.nome_completo, u.id as user_id
                FROM comentarios c
                JOIN users u ON c.user_id = u.id
                WHERE c.campanha_id = ?
                ORDER BY c.criado_em ASC
            ");
            $stmt->execute([$postId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'comments' => $comments];
            break;
        
        case 'fetch_posts':
            $salaId = (int)($_GET['sala_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT cp.*, c.nome as cand_nome, c.partido, c.foto as cand_foto,
                       u.nome_completo as author_nome,
                       (SELECT COUNT(*) FROM post_reacoes WHERE post_id = cp.id AND tipo = 'adorado') as total_adorados,
                       (SELECT COUNT(*) FROM post_reacoes WHERE post_id = cp.id AND tipo = 'hater') as total_haters,
                       (SELECT tipo FROM post_reacoes WHERE post_id = cp.id AND user_id = :userId1) as user_reacao,
                       (SELECT COUNT(*) FROM comentarios WHERE campanha_id = cp.id) as total_comentarios,
                       (SELECT COUNT(*) FROM retweets WHERE post_id = cp.id) as total_retweets,
                       (SELECT 1 FROM retweets WHERE post_id = cp.id AND user_id = :userId2) as user_retweeted
                FROM campanhas cp
                LEFT JOIN candidatos c ON cp.candidato_id = c.id
                LEFT JOIN users u ON cp.user_id = u.id
                WHERE cp.sala_id = :salaId
                ORDER BY cp.criado_em DESC
            ");
            $stmt->execute(['userId1' => $userId, 'userId2' => $userId, 'salaId' => $salaId]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'posts' => $posts];
            break;
            
        default:
            throw new Exception("Ação desconhecida: " . htmlspecialchars($action));
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

// CRITICAL: Clean any prior output (like PHP warnings or whitespace) before sending JSON
if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;
