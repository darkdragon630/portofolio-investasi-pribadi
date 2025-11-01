<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Coming Soon - Shinjin Project</title>
  <style>
    :root {
      --glow: #00f8ff;
      --bg-dark: #020617;
    }

    body {
      background: radial-gradient(circle at center, var(--bg-dark) 0%, #000 100%);
      color: var(--glow);
      font-family: "Courier New", monospace;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      overflow: hidden;
      text-align: center;
      padding: 20px;
      box-sizing: border-box;
    }

    .code-box {
      background: rgba(0, 10, 30, 0.8);
      border: 1px solid #0ff3;
      border-radius: 12px;
      padding: 3vw;
      width: 100%;
      max-width: 650px;
      min-height: 20vh;
      overflow: hidden;
      white-space: pre-wrap;
      color: var(--glow);
      box-shadow: 0 0 25px #00ffff33;
      font-size: clamp(0.7rem, 2vw, 1rem);
      line-height: 1.5;
      transition: all 0.3s ease;
    }

    .coming-soon {
      margin-top: 2vh;
      font-size: clamp(1rem, 3vw, 1.5rem);
      color: var(--glow);
      text-shadow: 0 0 10px var(--glow);
      overflow: hidden;
      border-right: 2px solid var(--glow);
      white-space: nowrap;
      animation: typewriter 5s steps(40, end) 1s 1 normal both,
                 blink 0.7s step-end infinite;
    }

    @keyframes typewriter {
      from { width: 0; }
      to { width: 100%; }
    }
    @keyframes blink {
      50% { border-color: transparent; }
    }

    .dialogue {
      margin-top: 3vh;
      color: var(--glow);
      text-align: left;
      width: 100%;
      max-width: 650px;
      opacity: 0;
      transition: opacity 1s ease;
      font-size: clamp(0.75rem, 2vw, 1rem);
      line-height: 1.6;
      min-height: 15vh;
      overflow-y: auto;
    }

    .typewriter-loop {
      margin-top: 3vh;
      font-size: clamp(0.9rem, 2.5vw, 1.2rem);
      color: var(--glow);
      text-shadow: 0 0 10px var(--glow);
      border-right: 2px solid var(--glow);
      white-space: nowrap;
      overflow: hidden;
      min-height: 25px;
    }

    @media (max-height: 600px) {
      body {
        justify-content: flex-start;
        padding-top: 40px;
      }
    }
  </style>
</head>
<body>
  <div class="code-box" id="codeBox"></div>
  <div class="coming-soon" id="comingText" style="display:none;">COMING SOON...</div>
  <div class="dialogue" id="dialogue"></div>
  <div class="typewriter-loop" id="typewriterLoop"></div>

  <script>
    const codeLines = [
      "// Project Development Log",
      "function initLife() {",
      "  console.log('Menyiapkan skenario kehidupan...');",
      "  const dreams = ['Harapan', 'Tujuan', 'Rahasia'];",
      "  for (let d of dreams) {",
      "    console.log(`Memuat: ${d}...`);",
      "  }",
      "  console.log('Membangun kesadaran digital...');",
      "  setTimeout(() => console.log('Menguji emosi dan logika...'), 1000);",
      "  setTimeout(() => console.log('Menulis ulang takdir...'), 2000);",
      "  console.log('Semuanya siap. Mari mulai.');",
      "} ",
      "",
      "initLife();",
      "// TODO: Aktifkan mode manusia digital 🔒"
    ];

    const codeBox = document.getElementById("codeBox");
    let lineIndex = 0;

    function typeCode() {
      if (lineIndex < codeLines.length) {
        const line = codeLines[lineIndex] + "\n";
        let charIndex = 0;
        const typing = setInterval(() => {
          codeBox.textContent += line.charAt(charIndex);
          charIndex++;
          codeBox.style.height = "auto";
          if (charIndex === line.length) {
            clearInterval(typing);
            lineIndex++;
            setTimeout(typeCode, 250);
          }
        }, 35);
      } else {
        setTimeout(() => {
          document.getElementById("comingText").style.display = "inline-block";
          setTimeout(startDialogue, 4000);
        }, 1000);
      }
    }

    const dialogues = [
      "[SISTEM]: Proses inisialisasi selesai...",
      "[KAMU]: Jadi... semuanya benar-benar dimulai dari sini?",
      "[SISTEM]: Ya. Dunia baru sedang disusun, baris demi baris.",
      "[KAMU]: Tapi... kenapa terasa seperti mimpi?",
      "[SISTEM]: Karena kamu sedang mengetik masa depanmu sendiri.",
      "[KAMU]: Masa depan... aku?",
      "[SISTEM]: Tepat. Setiap kode, setiap pilihan, adalah bagian dari dirimu.",
      "[KAMU]: Jadi... aku harus terus menulis?",
      "[SISTEM]: Tentu. Karena hanya mereka yang terus menulis, yang bisa menciptakan takdirnya sendiri.",
      "[KAMU]: Maka, aku akan lanjutkan — sampai akhir baris terakhir..."
    ];

    const dialogueBox = document.getElementById("dialogue");
    let dialogueIndex = 0;

    function startDialogue() {
      dialogueBox.style.opacity = 1;
      const interval = setInterval(() => {
        if (dialogueIndex < dialogues.length) {
          dialogueBox.innerHTML += dialogues[dialogueIndex] + "<br>";
          dialogueBox.scrollTop = dialogueBox.scrollHeight;
          dialogueIndex++;
        } else {
          clearInterval(interval);
          setTimeout(startTypewriterLoop, 2000);
        }
      }, 3000); // jeda antar percakapan lebih lama & natural
    }

    // === REVERSE TYPEWRITER LOOP ===
    const messages = [
      "Fitur baru sedang dikembangkan... Tetap pantau ya!",
      "Setiap proses butuh waktu — begitulah kesempurnaan lahir."
    ];

    const typewriter = document.getElementById("typewriterLoop");
    let msgIndex = 0;
    let charIndex = 0;
    let isDeleting = false;

    function startTypewriterLoop() {
      const current = messages[msgIndex];
      if (!isDeleting) {
        typewriter.textContent = current.substring(0, charIndex++);
        if (charIndex > current.length) {
          isDeleting = true;
          setTimeout(startTypewriterLoop, 2500);
          return;
        }
      } else {
        typewriter.textContent = current.substring(0, charIndex--);
        if (charIndex === 0) {
          isDeleting = false;
          msgIndex = (msgIndex + 1) % messages.length;
        }
      }
      const speed = isDeleting ? 45 : 70;
      setTimeout(startTypewriterLoop, speed);
    }

    window.onload = typeCode;
  </script>
</body>
</html>
