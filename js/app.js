;(function(){
  const LS_KEY = 'chat_conversations';
  let convs = [], activeIndex = -1;

  const el = {
    prov:  document.getElementById('provider'),
    save:  document.getElementById('btn-save'),
    load:  document.getElementById('btn-load'),
    add:   document.getElementById('btn-new'),
    list:  document.getElementById('conv-list'),
    msgs:  document.getElementById('msg-container'),
    input: document.getElementById('msg-input'),
    send:  document.getElementById('btn-send'),
    sidebar: document.getElementById('sidebar'),
    toggle:  document.getElementById('sidebar-toggle'),
    container: document.getElementById('container')
  };

  function init(){
    const raw = localStorage.getItem(LS_KEY);
    if (raw) {
      convs = JSON.parse(raw);
      renderList();
      selectConv(0);
    } else {
      loadFromServer();
    }
    bind();
    handleResize(); // 初始化宽度判断
  }

  function bind(){
    el.add.onclick = () => {
      const name = '对话 ' + (convs.length + 1);
      convs.push({ name, provider: '', messages: [] });
      saveLS(); renderList(); selectConv(convs.length - 1);
    };

    el.load.onclick = () => {
      localStorage.removeItem(LS_KEY);
      loadFromServer();
    };

    el.save.onclick = () => {
      fetch('./backend/conversations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(convs)
      })
      .then(r => r.json())
      .then(_ => alert('已保存'))
      .catch(e => alert('保存失败：' + e));
    };

    el.list.onclick = e => {
      if (e.target.dataset.idx != null) selectConv(+e.target.dataset.idx);
    };

    el.send.onclick = sendMessage;

    el.input.addEventListener('keydown', e => {
      if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
      }
    });

    el.toggle.addEventListener('click', () => {
      el.container.classList.toggle('sidebar-open');
    });

    window.addEventListener('resize', handleResize);
  }

  function handleResize(){
    const w = window.innerWidth;
    if (w >= 1024) {
      el.container.classList.remove('sidebar-open');
      el.sidebar.classList.remove('collapsed');
      el.toggle.style.display = 'none';
    } else {
      el.sidebar.classList.add('collapsed');
      el.toggle.style.display = 'block';
    }
  }

  function loadFromServer(){
    fetch('./backend/conversations.php')
      .then(r => r.json())
      .then(data => {
        convs = data || [];
        saveLS();
        renderList();
        if (convs.length) selectConv(0);
      })
      .catch(console.error);
  }

  function saveLS(){
    localStorage.setItem(LS_KEY, JSON.stringify(convs));
  }

  function renderList(){
    el.list.innerHTML = '';
    convs.forEach((c, i) => {
      const d = document.createElement('div');
      d.textContent = c.name;
      d.className = 'conv-item' + (i === activeIndex ? ' active' : '');
      d.dataset.idx = i;
      el.list.append(d);
    });
  }

  function selectConv(i){
    activeIndex = i;
    const cv = convs[i];
    el.prov.value = cv.provider;
    renderMsgs(cv.messages);
    renderList();
  }

  function renderMsgs(msgs){
    el.msgs.innerHTML = '';
    msgs.forEach(m => {
      const d = document.createElement('div');
      d.className = 'msg ' + m.role + (m.loading ? ' loading' : '');

      if (m.loading) {
        const sp = document.createElement('div');
        sp.className = 'spinner';
        d.append(sp);
      } else {
        if (m.role === 'assistant') {
          const html = marked.parse(m.text || '');
          d.innerHTML = DOMPurify.sanitize(html);
        } else {
          d.textContent = m.text;
        }
      }

      el.msgs.appendChild(d);
    });
    el.msgs.scrollTop = el.msgs.scrollHeight;
  }

  function sendMessage(){
    const txt = el.input.value.trim();
    if (!txt) {
      alert('请输入内容后再发送哦～');
      return;
    }
    if (activeIndex < 0) return;

    const cv = convs[activeIndex];
    el.send.disabled = true;

    cv.messages.push({ role: 'user', text: txt });
    cv.messages.push({ role: 'assistant', text: '', loading: true });
    renderMsgs(cv.messages);
    saveLS();
    el.input.value = '';

    const payload = {
      provider: el.prov.value.trim(),
      conversation: cv.messages
        .filter(m => !m.loading)
        .map(m => ({ role: m.role, text: m.text }))
    };

    fetch('./backend/dispatcher.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
	.then(res => {
	  cv.messages.pop(); // 移除 loading

	  let reply = '';
	  if (res.choices?.[0]?.message?.content) {
		reply = res.choices[0].message.content;
	  } else if (res.error) {
		reply = res.error;
	  } else {
		reply = '其它错误';
	  }

	  cv.messages.push({ role: 'assistant', text: reply });
	  renderMsgs(cv.messages);
	  saveLS();
	})
    .catch(err => {
      cv.messages.pop();
      cv.messages.push({ role: 'assistant', text: '请求失败：' + err });
      renderMsgs(cv.messages);
      saveLS();
    })
    .finally(() => {
      el.send.disabled = false;
    });
  }

  init();
})();
