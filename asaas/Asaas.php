<?php
class Asaas {
    
    private $apiKey;
    private $baseUrl;
    private $environment;
    
    public function __construct() {
        $this->environment = env('ASAAS_ENV', 'sandbox');
        
        if ($this->environment === 'sandbox') {
            $this->apiKey = env('ASAAS_API_KEY_SANDBOX');
            $this->baseUrl = env('ASAAS_URL_SANDBOX');
        } else {
            $this->apiKey = env('ASAAS_API_KEY_PRODUCTION');
            $this->baseUrl = env('ASAAS_URL_PRODUCTION');
        }
        
        if (empty($this->apiKey)) {
            throw new Exception("API Key do Asaas não configurada no .env");
        }
    }
    
    private function request($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'access_token: ' . $this->apiKey,
            'User-Agent: MeuSite/1.0'  
];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $result['errors'][0]['description'] ?? 'Erro desconhecido';
            throw new Exception("Erro API Asaas: " . $errorMsg);
        }
        
        return $result;
    }
    
    public function criarCliente($dados) {
        $dadosCliente = [
            'name' => $dados['name'],
            'email' => $dados['email'] ?? null,
            'cpfCnpj' => $dados['cpfCnpj'] ?? null,
            'phone' => $dados['phone'] ?? null,
            'mobilePhone' => $dados['mobilePhone'] ?? null,
        ];
        
        $dadosCliente = array_filter($dadosCliente, function($v) {
            return $v !== null;
        });
        
        return $this->request('POST', '/customers', $dadosCliente);
    }
    
    public function criarCobrancaPix($customerId, $valor, $descricao = '') {
        $dados = [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $valor,
            'dueDate' => date('Y-m-d', strtotime('+1 day')),
        ];
        
        if (!empty($descricao)) {
            $dados['description'] = $descricao;
        }
        
        return $this->request('POST', '/payments', $dados);
    }
    
    public function gerarQRCodePix($paymentId) {
        return $this->request('GET', '/payments/' . $paymentId . '/pixQrCode');
    }
    
    public function buscarCobranca($paymentId) {
        return $this->request('GET', '/payments/' . $paymentId);
    }
    
    public function isPago($paymentId) {
        $cobranca = $this->buscarCobranca($paymentId);
        return in_array($cobranca['status'], ['RECEIVED', 'CONFIRMED']);
    }

    public function listarCobrancas($limit = 10) {
        return $this->request('GET', '/payments?limit=' . $limit);
    }

    public function consultarSaldo() {
        return $this->request('GET', '/finance/balance');
    }
}
?>