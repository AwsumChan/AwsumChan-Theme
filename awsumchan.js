/**
 * awsumchan.js
 * @author Circlepuller <admin@awsumchan.org>
 */

function init_nsfw()
{
  var nsfw = localStorage.getItem('awsumchan-nsfw-toggle') || false;

  if (nsfw) {
    document.getElementById('toggle-nsfw').textContent = '[hide nsfw content]';
    document.querySelectorAll('li.nsfw').forEach(i => i.style.display = 'block');
  }
}

function toggle_nsfw()
{
  var nsfw = localStorage.getItem('awsumchan-nsfw-toggle') || false;

  document.getElementById('toggle-nsfw').textContent = '[' + (nsfw ? 'show' : 'hide') + ' nsfw content]';
  document.querySelectorAll('li.nsfw').forEach(i => i.style.display = (nsfw ? 'none': 'block'));
  localStorage.setItem('awsumchan-nsfw-toggle', !nsfw);
}

document.onload = init_nsfw;
document.getElementById('toggle-nsfw').onclick = toggle_nsfw;