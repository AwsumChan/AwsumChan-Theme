/**
 * awsumchan.js
 * @author Circlepuller <admin@awsumchan.org>
 */

function init_nsfw()
{
  let nsfw = localStorage.getItem('awsumchan-nsfw-toggle') || 0;

  if (nsfw == 1) {
    document.getElementById('toggle-nsfw').textContent = '[hide nsfw content]';
    document.querySelectorAll('li.nsfw').forEach(i => i.style.display = 'block');
  }

  document.getElementById('toggle-nsfw').onclick = toggle_nsfw;
}

function toggle_nsfw()
{
  let nsfw = localStorage.getItem('awsumchan-nsfw-toggle') || 0;

  document.getElementById('toggle-nsfw').textContent = '[' + (nsfw == 1 ? 'show' : 'hide') + ' nsfw content]';
  document.querySelectorAll('li.nsfw').forEach(i => i.style.display = (nsfw == 1 ? 'none': 'block'));
  localStorage.setItem('awsumchan-nsfw-toggle', nsfw == 1 ? 0 : 1);
}

window.onload = init_nsfw;