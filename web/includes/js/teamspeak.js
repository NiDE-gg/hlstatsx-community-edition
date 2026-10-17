/**
 * Teamspeak "connect" links.
 *
 * pages/teamspeak_class.php used to echo() a per-server set of inline
 * <script> functions (stringOk_<hash>(), enterServer_<hash>(),
 * enterChannel_<hash>(), enterSubChannel_<hash>()) with the server address,
 * forbidden nickname characters and server/channel names interpolated
 * straight into the generated JS source (through jsEscape(), but still JS
 * source built by PHP string concatenation).
 *
 * This file replaces that: each server's settings are published once as a
 * JSON payload (`<script type="application/json" id="teamspeak-settings-...">`)
 * and every "connect" link carries `data-ts-*` attributes pointing at it.
 * Clicking a link reads the JSON and runs the same prompt/redirect logic the
 * old inline functions used to run.
 */
(function () {
	'use strict';

	function readSettings(id) {
		var el = document.getElementById(id);
		if (!el) {
			return null;
		}
		try {
			return JSON.parse(el.textContent);
		} catch (e) {
			console.error('Teamspeak: invalid settings payload for #' + id, e);
			return null;
		}
	}

	function stringOk(string, forbiddenChars) {
		for (var i = 0; i < string.length; i++) {
			if (forbiddenChars.indexOf(string.charAt(i)) > -1) {
				return false;
			}
		}
		return true;
	}

	// Mirrors the old enterSubChannel_<hash>(channelName, channelPassworded,
	// subChannelName) function; enterServer()/enterChannel() just called this
	// with some arguments forced to null/false.
	function enterSubChannel(settings, channelName, channelPassworded, subChannelName) {
		var serveraddress = 'teamspeak://' + settings.serveraddress + ':' + settings.serverudpport;

		var nickname = window.prompt('Enter your nickname', '');
		if (nickname === null) {
			return;
		} else if (!stringOk(nickname, settings.forbiddennicknamechars)) {
			window.alert('Could not enter the teamspeak server because the nickname you entered contains one or more of these forbidden characters: ' + settings.forbiddennicknamechars);
			return;
		} else if (nickname === '') {
			window.alert('Could not enter the teamspeak server because you did not enter your nickname');
			return;
		}

		serveraddress = serveraddress + '/nickname=' + encodeURIComponent(nickname);

		if (settings.serverPasswordRequired) {
			var password = window.prompt('Enter the teamspeak server password for ' + settings.serverName, '');
			if (password === null) {
				return;
			} else if (password === '') {
				window.alert('Could not enter the teamspeak server because you did not enter a server password');
				return;
			}
			serveraddress = serveraddress + '?password=' + encodeURIComponent(password);
		}

		if (channelName !== null && channelName !== undefined) {
			serveraddress = serveraddress + '?channel=' + encodeURIComponent(channelName);
		}

		if (channelPassworded) {
			var channelpassword = window.prompt('Enter the channel password for channel ' + channelName, '');
			if (channelpassword === null) {
				return;
			} else if (channelpassword === '') {
				window.alert('Could not enter the teamspeak server because you did not enter a channel password');
				return;
			}
			serveraddress = serveraddress + '?channelpassword=' + encodeURIComponent(channelpassword);
		}

		if (subChannelName !== null && subChannelName !== undefined) {
			serveraddress = serveraddress + '?subchannel=' + encodeURIComponent(subChannelName);
		}

		window.location = serveraddress;
	}

	document.addEventListener('click', function (e) {
		var link = e.target.closest ? e.target.closest('[data-ts-action]') : null;
		if (!link) {
			return;
		}

		e.preventDefault();

		var settings = readSettings(link.getAttribute('data-ts-settings'));
		if (!settings) {
			return;
		}

		var action = link.getAttribute('data-ts-action');
		var channelName = link.hasAttribute('data-ts-channel') ? link.getAttribute('data-ts-channel') : null;
		var channelPassworded = link.getAttribute('data-ts-channel-passworded') === 'true';
		var subChannelName = link.hasAttribute('data-ts-subchannel') ? link.getAttribute('data-ts-subchannel') : null;

		if (action === 'server') {
			enterSubChannel(settings, null, false, null);
		} else if (action === 'channel') {
			enterSubChannel(settings, channelName, channelPassworded, null);
		} else if (action === 'subchannel') {
			enterSubChannel(settings, channelName, channelPassworded, subChannelName);
		}
	});
})();
