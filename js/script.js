console.log("blub");

const heute = new Date();

    const monat = heute.toLocaleDateString('de-DE', { month: 'long' });
    const tag = heute.getDate();

    document.getElementById('monat').textContent = monat;
    document.getElementById('tag').textContent = tag;