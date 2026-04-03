<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Signed Successfully!</title>
    @vite(['resources/css/app.css'])
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 560px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            font-size: 56px;
            color: white;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
            animation: pop 0.5s ease-out 0.3s both;
        }
        @keyframes pop {
            0% { transform: scale(0.3); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        h1 {
            font-size: 32px;
            color: #1f2937;
            margin-bottom: 16px;
            font-weight: 700;
        }
        p {
            font-size: 16px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .info-box {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
            text-align: left;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .info-item:last-child {
            margin-bottom: 0;
        }
        .info-icon {
            font-size: 20px;
        }
        .info-text {
            font-size: 14px;
            color: #374151;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        canvas {
            position: fixed;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 999;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">✓</div>
        
        <h1>Document Signed Successfully!</h1>
        
        <p>Your signature has been recorded and the document has been completed. You will receive a confirmation email shortly.</p>
        
        <div class="info-box">
            <div class="info-item">
                <span class="info-icon">📧</span>
                <span class="info-text">Confirmation email sent to your inbox</span>
            </div>
            <div class="info-item">
                <span class="info-icon">🔒</span>
                <span class="info-text">Document secured with encryption</span>
            </div>
            <div class="info-item">
                <span class="info-icon">💾</span>
                <span class="info-text">Signed copy available for download</span>
            </div>
        </div>
        
        <a href="{{ route('signatures.index') }}" class="btn">
            Return to Dashboard
        </a>
    </div>
    
    <!-- Confetti Animation -->
    <canvas id="confetti"></canvas>
    <script>
        // Lightweight confetti
        const canvas = document.getElementById('confetti');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        const confetti = [];
        const colors = ['#60a5fa', '#34d399', '#fbbf24', '#f472b6', '#a78bfa'];
        
        for (let i = 0; i < 120; i++) {
            confetti.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height - canvas.height,
                r: 4 + Math.random() * 6,
                d: Math.random() * 2 + 1,
                color: colors[Math.floor(Math.random() * colors.length)],
                tilt: Math.floor(Math.random() * 10) - 10
            });
        }
        
        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            confetti.forEach((p, i) => {
                ctx.beginPath();
                ctx.lineWidth = p.r / 2;
                ctx.strokeStyle = p.color;
                ctx.moveTo(p.x + p.tilt + p.r, p.y);
                ctx.lineTo(p.x + p.tilt, p.y + p.r);
                ctx.stroke();
                
                p.y += p.d;
                p.tilt = Math.sin(p.y * 0.02) * 10;
                
                if (p.y > canvas.height) {
                    confetti[i] = {
                        x: Math.random() * canvas.width,
                        y: -20,
                        r: p.r,
                        d: p.d,
                        color: p.color,
                        tilt: Math.floor(Math.random() * 10) - 10
                    };
                }
            });
            
            requestAnimationFrame(draw);
        }
        
        draw();
        
        // Stop after 8 seconds
        setTimeout(() => {
            const fadeOut = setInterval(() => {
                confetti.pop();
                if (confetti.length === 0) clearInterval(fadeOut);
            }, 20);
        }, 8000);
    </script>
</body>
</html>
