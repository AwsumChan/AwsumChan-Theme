/**
 * awsumchan.js
 * @author Circlepuller <admin@awsumchan.org>
 */

function init_nsfw()
{
  var nsfw = localStorage.getItem('awsumchan-nsfw-toggle') || false;

  if (nsfw === true) {
    document.getElementById('toggle-nsfw').textContent = '[hide nsfw content]';
    document.querySelectorAll('li.nsfw').forEach(i => i.style.display = 'block');
  }

  document.getElementById('toggle-nsfw').onclick = toggle_nsfw;
}

function toggle_nsfw()
{
  var nsfw = localStorage.getItem('awsumchan-nsfw-toggle') || false;

  document.getElementById('toggle-nsfw').textContent = '[' + (nsfw === true ? 'show' : 'hide') + ' nsfw content]';
  document.querySelectorAll('li.nsfw').forEach(i => i.style.display = (nsfw === true ? 'none': 'block'));
  localStorage.setItem('awsumchan-nsfw-toggle', nsfw === true ? false : true);
}

document.onload = init_nsfw;