<?php
/**
 * Test que simula EXACTAMENTE la llamada del navegador
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test API Directo</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 20px auto; padding: 20px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .error { background: #f8d7da; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h2>🧪 Test API verificar_token.php</h2>
    <hr>
    
    <h3>Test con AJAX (como el navegador):</h3>
    
    <form id="testForm">
        <label>Token (4 dígitos):</label><br>
        <input type="text" id="token" value="1234" maxlength="4" style="padding: 10px; margin: 10px 0;"><br>
        
        <label>Email:</label><br>
        <input type="email" id="email" value="gtomasifnew@gmail.com" style="padding: 10px; margin: 10px 0; width: 300px;"><br>
        
        <button type="submit">Probar Verificación</button>
    </form>
    
    <div id="resultado"></div>
    
    <hr>
    <h3>Test con CURL (desde servidor):</h3>
    <button onclick="testCURL()">Probar con CURL</button>
    <div id="resultadoCURL"></div>
    
    <script>
        // Test con AJAX
        document.getElementById('testForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const token = document.getElementById('token').value;
            const email = document.getElementById('email').value;
            const resultDiv = document.getElementById('resultado');
            
            resultDiv.innerHTML = '<p>⏳ Enviando...</p>';
            
            try {
                const response = await fetch('api/verificar_token.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ token, email })
                });
                
                console.log('Status:', response.status);
                console.log('Headers:', response.headers);
                
                const text = await response.text();
                console.log('Respuesta raw:', text);
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <p><strong>Status HTTP:</strong> ${response.status}</p>
                        <p><strong>Content-Type:</strong> ${response.headers.get('content-type')}</p>
                    </div>
                    <p><strong>Respuesta del servidor (RAW):</strong></p>
                    <pre>${text.substring(0, 1000)}</pre>
                `;
                
                // Intentar parsear JSON
                try {
                    const json = JSON.parse(text);
                    resultDiv.innerHTML += `
                        <div class="success">
                            <p><strong>✅ JSON válido:</strong></p>
                            <pre>${JSON.stringify(json, null, 2)}</pre>
                        </div>
                    `;
                } catch (e) {
                    resultDiv.innerHTML += `
                        <div class="error">
                            <p><strong>❌ NO es JSON válido</strong></p>
                            <p>Error: ${e.message}</p>
                            <p>Primeros caracteres (códigos ASCII):</p>
                            <pre>${
                                text.substring(0, 100).split('').map((c, i) => 
                                    `[${i}] ${c.charCodeAt(0)}: ${c === '\n' ? '\\n' : c === '\r' ? '\\r' : c}`
                                ).join('\n')
                            }</pre>
                        </div>
                    `;
                }
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <p><strong>❌ Error:</strong></p>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        });
        
        // Test con CURL desde PHP
        async function testCURL() {
            const resultDiv = document.getElementById('resultadoCURL');
            resultDiv.innerHTML = '<p>⏳ Ejecutando CURL desde servidor...</p>';
            
            try {
                const response = await fetch('test_curl_api.php');
                const html = await response.text();
                resultDiv.innerHTML = html;
            } catch (error) {
                resultDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            }
        }
    </script>
</body>
</html>