<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeeTech – ترحيب</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Arial',sans-serif;
            background:#0a0a0a;               /* أسود كامل */
            height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            overflow:hidden;
            color:#f5f5f5;
        }
        .container{
            text-align:center;
            animation:fadeIn 1.2s ease-out;
        }
        @keyframes fadeIn{
            from{opacity:0;transform:translateY(-25px)}
            to{opacity:1;transform:translateY(0)}
        }
        .bee-icon{
            font-size:70px;
            margin-bottom:15px;
            animation:buzz .6s infinite alternate;
            filter:drop-shadow(0 0 8px #ff6b35);   /* برتقالي */
        }
        @keyframes buzz{
            0%{transform:rotate(-4deg)}
            100%{transform:rotate(4deg)}
        }
        h1{
            font-size:2.8em;
            letter-spacing:1px;
            margin-bottom:8px;
            color:#fff;
        }
        .subtitle{
            font-size:1.3em;
            color:#aaa;
            margin-bottom:6px;
        }
        .project{
            font-size:1.1em;
            color:#ccc;
            margin-bottom:35px;
        }
        .login-btn{
            display:inline-block;
            padding:14px 36px;
            background:#ff6b35;               /* برتقالي */
            color:#000;                       /* نص أسود لتباين عالي */
            border:none;
            border-radius:30px;
            font-size:1.1em;
            font-weight:bold;
            cursor:pointer;
            transition:all .3s ease;
            box-shadow:0 4px 14px rgba(255,107,53,.45);
        }
        .login-btn:hover{
            background:#ff8555;
            transform:translateY(-2px);
            box-shadow:0 6px 20px rgba(255,107,53,.6);
        }
        .copyright{
            position:absolute;
            bottom:18px;
            left:50%;
            transform:translateX(-50%);
            font-size:.75em;
            color:#666;
            text-align:center;
            line-height:1.4;
        }
        /* جزيئات برتقالية خفيفة */
        .particles{
            position:absolute;
            width:100%;height:100%;
            top:0;left:0;
            pointer-events:none;
        }
        .particle{
            position:absolute;
            width:3px;height:3px;
            background:rgba(255,107,53,.35);   /* برتقالي شفاف */
            border-radius:50%;
            animation:float 4s infinite ease-in-out;
        }
        @keyframes float{
            0%,100%{transform:translateY(0) translateX(0);opacity:0}
            10%{opacity:1}
            90%{opacity:1}
            100%{transform:translateY(-120px) translateX(120px);opacity:0}
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>

    <div class="container">
        <div class="bee-icon">🐝</div>
        <h1>BeeTech</h1>
        <p class="subtitle">فريق تطوير البرمجيات</p>
        <p class="project">مشروع إدارة المستودعات</p>
        <button class="login-btn" onclick="goLogin()">تسجيل الدخول</button>
    </div>

    <div class="copyright">
        جميع حقوق الاستخدام والملكية الفكرية لهذا البرنامج محفوظة لـ &copy; BeeTech Team 2025.<br>
        يُمنع الاستخدام أو التعديل أو التوزيع دون تصريح خطي مسبق من الفريق.
    </div>

    <script>
        // إنشاء جزيئات خلفية
        const pContainer=document.getElementById('particles');
        for(let i=0;i<25;i++){
            const dot=document.createElement('div');
            dot.className='particle';
            dot.style.left=Math.random()*100+'%';
            dot.style.animationDelay=Math.random()*4+'s';
            dot.style.animationDuration=(Math.random()*3+3)+'s';
            pContainer.appendChild(dot);
        }
        // الانتقال لصفحة تسجيل الدخول
        function goLogin(){
            const btn=document.querySelector('.login-btn');
            btn.style.transform='scale(0.96)';
            btn.style.opacity='0.8';
            setTimeout(()=>window.location.href='http://127.0.0.1:8080/admin',200);
        }
    </script>
</body>
</html>
