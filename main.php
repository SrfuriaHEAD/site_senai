<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

$mensagem_sucesso = "";
$mensagem_erro = "";
$adm = __DIR__ . "/db/admin.db";
$log = __DIR__ . "/db/log.db"; 
$bd = __DIR__ . "/db/login.db";  
$op = fopen($log, "a");
$name = "";

if (!is_dir('./db')) {
    mkdir('./db', 0777, true);
}

if (file_exists($log) == false) {
    file_put_contents($log, "Log de mensagens:\n");
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: main.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nome']) && isset($_POST['senha'])) {
    $name = trim($_POST['nome'] ?? '');
    $pass = trim($_POST['senha'] ?? '');
    $pass = strip_tags($pass);
    $name = strip_tags($name);
    
    if (mb_strlen($pass) < 6) {
        $mensagem_erro = "A senha deve conter pelo menos 6 caracteres.";
    } elseif (mb_strlen($name) < 1 || mb_strlen($name) > 50) {
        $mensagem_erro = "O nome deve conter entre 1 e 50 caracteres.";
    } else {
        $conteudoAtual = "";
        if (file_exists($bd)) {
            $conteudoAtual = file_get_contents($bd);
        }
        if (file_exists($adm)) {
            $conteudoAtual .= "\n" . file_get_contents($adm);
        }
        
        $nomeParaBuscar = "Nome: " . $name;
        if ($conteudoAtual !== "" && str_contains($conteudoAtual, $nomeParaBuscar)) {
            $match = false;
            $isAdmin = false;
            foreach (explode("\n", $conteudoAtual) as $line) {
                if (str_contains($line, "Nome: $name") && str_contains($line, "Senha: $pass")) {
                    $match = true;
                    break;
                }
            }
            if ($match && file_exists($adm)) {
                $adminContent = file_get_contents($adm);
                if (str_contains($adminContent, "Nome: $name") && str_contains($adminContent, "Senha: $pass")) {
                    $isAdmin = true;
                }
            }

            if ($match) {
                $_SESSION['usuario_nome'] = $name;
                $_SESSION['usuario_logado'] = true;
                if ($isAdmin) {
                    $_SESSION['usuario_admin'] = true;
                }
                
                $mensagem_sucesso = "Login bem-sucedido!";
            } else {
                $mensagem_erro = "Senha incorreta para o nome fornecido.";
            }
        } else {
            $data = date('Y-m-d H:i:s', strtotime('-4 hours'));
            $linha = "Novo cadastro: $data | Nome: $name | Senha: $pass\n";
            
            $bytes_gravados = file_put_contents($bd, $linha, FILE_APPEND | LOCK_EX);
            
            if ($bytes_gravados !== false && $bytes_gravados > 0) {
                $_SESSION['usuario_nome'] = $name;
                $_SESSION['usuario_logado'] = true;
                
                $mensagem_sucesso = "Sucesso: Nome cadastrado! Login bem-sucedido!";
                $name = "";
            } else {
                $mensagem_erro = "Erro ao gravar no arquivo. Verifique as permissões da pasta.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name'])) {
    $nome_contato = htmlspecialchars($_POST['name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $mensagem = htmlspecialchars($_POST['message'] ?? '');
    if (!empty($nome_contato)) {
        $mensagem_sucesso = "Obrigado, $nome_contato! Recebemos sua mensagem.";
    }
}

$usuario_logado = isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'];
$nome_usuario = $_SESSION['usuario_nome'] ?? '';

$url = $_GET['url'] ?? '/';


switch ($url) {
    case '/':
    case '/index':
        ob_start();
        include __DIR__ . '/template/index.html';
        $html = ob_get_clean();
        
        if ($usuario_logado) {
            $nav_user = '<li style="float: right;"><span style="color: #fff; margin-right: 15px;">Olá, ' . htmlspecialchars($nome_usuario) . '!</span><a href="main.php?logout=1">Sair</a></li>';
            $html = preg_replace('/<\/ul>/', $nav_user . '</ul>', $html, 1);
        }
        
        echo $html;
        if ($op) fwrite($op, "Nova visita: " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
        break;

    case 'pagar':
        $valor_selecionado = $_POST['valor'] ?? $_GET['valor'] ?? '0';
        
        $html_identificacao = file_get_contents(__DIR__ . '/template/pagar.html'); 
        $html_identificacao = str_replace('{{valor}}', htmlspecialchars($valor_selecionado), $html_identificacao);
        $html_identificacao = str_replace('{{nome_sessao}}', htmlspecialchars($nome_usuario), $html_identificacao);
        
        echo $html_identificacao;
        break;

        case 'processar_pagamento':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                require_once __DIR__ . '/asaas/Asaas.php'; // Adicione esta linha aqui
                $asaas = new Asaas();

                $cliente = $asaas->criarCliente([
                    'name' => $_POST['nome'],
                    'cpfCnpj' => str_replace(['.', '-'], '', $_POST['cpfCnpj'])
                ]);
                
                $cobranca = $asaas->criarCobrancaPix(
                    $cliente['id'],
                    floatval($_POST['valor']),
                    'Apoio ao site'
                );
                
                $pix = $asaas->gerarQRCodePix($cobranca['id']);
                
                $pix_qrcode = $pix['encodedImage']; 
                $pix_payload = $pix['payload'];
                $payment_id = $cobranca['id'];
                $nome_cliente = $_POST['nome'];
                $valor = $_POST['valor'];

                include __DIR__ . '/template/qrcode_pix.html';
                exit; 

            } catch (Exception $e) {
                die("Erro no PIX: " . $e->getMessage());
            }
        }
        break;
    case 'sucesso':
        $payment_id = $_GET['payment_id'] ?? '';
        $valor = 50;
        include __DIR__ . '/template/sucesso.html';
        break;

    case 'about':
        ob_start();
        include __DIR__ . '/template/about.html';
        $html = ob_get_clean();
        
        if ($usuario_logado) {
            $nav_user = '<li style="float: right;"><span style="color: #fff; margin-right: 15px;">Olá, ' . htmlspecialchars($nome_usuario) . '!</span><a href="main.php?logout=1">Sair</a></li>';
            $html = preg_replace('/<\/ul>/', $nav_user . '</ul>', $html, 1);
        }
        
        echo $html;
        if ($op) fwrite($op, "Visita à página 'About': " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
        break;
    
    case 'services':
        ob_start();
        include __DIR__ . '/template/services.html';
        $html = ob_get_clean();
        
        if ($usuario_logado) {
            $nav_user = '<li style="float: right;"><span style="color: #fff; margin-right: 15px;">Olá, ' . htmlspecialchars($nome_usuario) . '!</span><a href="main.php?logout=1">Sair</a></li>';
            $html = preg_replace('/<\/ul>/', $nav_user . '</ul>', $html, 1);
        }
        
        echo $html;
        if ($op) fwrite($op, "Visita à página 'Services': " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
        break;
    
    case 'contact':
        if ($mensagem_sucesso !== "") {
            echo "<script>alert('" . addslashes($mensagem_sucesso) . "');</script>";
            echo "<div style='background:#eee; padding:10px;'>Dados recebidos: " . htmlspecialchars($nome_contato ?? '') . " (" . htmlspecialchars($email ?? '') . ")</div>";
        }
        
        ob_start();
        include __DIR__ . '/template/contact.html';
        $html = ob_get_clean();
        
        if ($usuario_logado) {
            $nav_user = '<li style="float: right;"><span style="color: #fff; margin-right: 15px;">Olá, ' . htmlspecialchars($nome_usuario) . '!</span><a href="main.php?logout=1">Sair</a></li>';
            $html = preg_replace('/<\/ul>/', $nav_user . '</ul>', $html, 1);
        }
        
        echo $html;
        if ($op) fwrite($op, "Visita à página 'Contact': " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
        break;
        
    case 'login':
        if ($mensagem_erro !== "") {
            echo "<script>alert('" . addslashes($mensagem_erro) . "');</script>";
        }
        if ($mensagem_sucesso !== "") {
            echo "<script>alert('" . addslashes($mensagem_sucesso) . "');</script>";
        }
        
        $login_html = file_get_contents(__DIR__ . '/template/login.html');
        $login_html = str_replace("{{nome}}", htmlspecialchars($name), $login_html);
        
        if ($usuario_logado) {
            $nav_user = '<li style="float: right;"><span style="color: #fff; margin-right: 15px;">Olá, ' . htmlspecialchars($nome_usuario) . '!</span><a href="main.php?logout=1">Sair</a></li>';
            $login_html = preg_replace('/<\/ul>/', $nav_user . '</ul>', $login_html, 1);
        }
        
        echo $login_html;
        if ($op) fwrite($op, "Visita à página 'Login': " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
        break;

        case 'cadastrar':
            if ($mensagem_erro !== "") {
                echo "<script>alert('" . addslashes($mensagem_erro) . "');</script>";
            }
            if ($mensagem_sucesso !== "") {
                echo "<script>alert('" . addslashes($mensagem_sucesso) . "');</script>";
            }
            $cadastrar_html = file_get_contents(__DIR__ . '/template/cadastrar.html');
            $cadastrar_html = str_replace("{{nome}}", htmlspecialchars($name), $cadastrar_html);
            if ($usuario_logado) {
                $nav_user = '<li style="float: right;"><span style="color: #fff; margin-right: 15px;">Olá, ' . htmlspecialchars($nome_usuario) . '!</span><a href="main.php?logout=1">Sair</a></li>';
                $cadastrar_html = preg_replace('/<\/ul>/', $nav_user . '</ul>', $cadastrar_html, 1);
            }
            echo $cadastrar_html;
            if ($op) fwrite($op, "Visita à página 'Cadastrar': " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
            break;
    
    case 'logout':
        session_destroy();
        header("Location: main.php");
        exit();
        break;
        
    case 'admin':
    $isAdminPage = false;
    if ($usuario_logado && file_exists($adm)) {
        $adminContent = file_get_contents($adm);
        if (str_contains($adminContent, "Nome: $nome_usuario")) {
            $isAdminPage = true;
        }
    }

    if (!$isAdminPage) {
        include __DIR__ . '/template/404.html';
        echo "<script>alert('Acesso negado: Você não tem permissão para acessar esta página.');</script>";
        if ($op) fwrite($op, "Tentativa de acesso à página 'Admin' - usuário: " . ($nome_usuario ?: "não logado") . " - " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
        break;
    }

    try {
        require_once __DIR__ . '/asaas/Asaas.php';
        $asaas = new Asaas();

        $saldoData = $asaas->consultarSaldo();
        $listaPagamentos = $asaas->listarCobrancas(50); 
        $saldoFinal = "R$ " . number_format($saldoData['balance'] ?? 0, 2, ',', '.');

        $lucroTotal = 0;
        $qtdPagos = 0;
        $qtdPendente = 0;
        $qtdRecusado = 0;
        $valorPendente = 0;

        $tabela = "<table><thead><tr><th>Data/Hora</th><th>Cliente</th><th>Valor</th><th>Status</th></tr></thead><tbody>";

        foreach ($listaPagamentos['data'] as $pg) {
            $valor = $pg['value'];
            $status = $pg['status'];

            if ($status === 'RECEIVED' || $status === 'CONFIRMED') {
                $lucroTotal += $valor;
                $qtdPagos++;
            } elseif ($status === 'PENDING') {
                $qtdPendente++;
                $valorPendente += $valor;
            } else {
                $qtdRecusado++;
            }

            $dataBR = date('d/m/Y H:i', strtotime($pg['dateCreated']));
            $tabela .= "
            <tr>
                <td>$dataBR</td>
                <td><code>{$pg['customer']}</code></td>
                <td>R$ " . number_format($valor, 2, ',', '.') . "</td>
                <td><span class='status-badge status-$status'>$status</span></td>
            </tr>";
        }
        $tabela .= "</tbody></table>";

        $html = file_get_contents(__DIR__ . '/template/admin.html');

        $html = str_replace('{{saldo_asaas}}', $saldoFinal, $html);
        $html = str_replace('{{tabela_pagamentos}}', $tabela, $html);
        $html = str_replace('{{lucro_real}}', $lucroTotal, $html);
        $html = str_replace('{{qtd_pagos}}', $qtdPagos, $html);
        $html = str_replace('{{qtd_pendentes}}', $qtdPendente, $html);
        $html = str_replace('{{qtd_recusados}}', $qtdRecusado, $html);

        $tabela = "<table><thead><tr><th>Data/Hora</th><th>Cliente</th><th>Valor</th><th>Status</th></tr></thead><tbody>";

        foreach ($listaPagamentos['data'] as $pg) {
            $valor = $pg['value'];
            $status = $pg['status'];

            $dataBR = date('d/m/Y H:i', strtotime($pg['dateCreated'])); 
            
            $tabela .= "<tr>
                <td>$dataBR</td>
                <td><code>{$pg['customer']}</code></td>
                <td>R$ " . number_format($valor, 2, ',', '.') . "</td>
                <td><span class='status-badge status-$status'>$status</span></td>
            </tr>";
        }
        echo $html;
        exit;

    } catch (Exception $e) {
        die("Erro: " . $e->getMessage());
    }
    break;
        
    default:
        include __DIR__ . '/template/404.html';
        if ($op) fwrite($op, "Visita à página desconhecida: " . date('Y-m-d H:i:s', strtotime('-4 hours')) . "\n");
        break;
}

if ($op) fclose($op);
?>