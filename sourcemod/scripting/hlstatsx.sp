/**
 * HLstatsX Community Edition - SourceMod plugin to display ingame messages
 * http://www.hlxcommunity.com/
 * Copyright (C) 2008-2009 Nicholas Hastings
 * Copyright (C) 2007-2009 TTS Oetzel & Goerz GmbH
 * Modified by Nicholas Hastings (psychonic) for use with HLstatsX Community Edition
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>
#include <sdktools>
#include <loghelper>
#undef REQUIRE_EXTENSIONS
#include <cstrike>
#include <clientprefs>
#define REQUIRE_EXTENSIONS

#define VERSION "1.7.0"
#define HLXTAG "HLstatsX:CE"
#define SVTAGSIZE 128

// Menu items that open a submenu instead of sending a command to the daemon
#define MENU_AUTO "#auto"
#define MENU_EVENTS "#events"

enum GameType {
	Game_Unknown = -1,
	Game_CSS,
	Game_DODS,
	Game_L4D,
	Game_TF,
	Game_HL2MP,
	Game_INSMOD,
	Game_FF,
	Game_ZPS,
	Game_AOC,
	Game_FOF,
	Game_GES,
	Game_PVKII,
	Game_CSP,
	Game_ND,
	Game_DDD,
};

GameType gamemod = Game_Unknown;

ConVar hlx_block_chat_commands;
ConVar hlx_message_prefix;
ConVar hlx_protect_address;
ConVar hlx_server_tag;
ConVar hlx_block_status;
ConVar sv_tags;

static const char blocked_commands[][] = { "rank", "skill", "points", "place", "session", "session_data",
                                     "kpd", "kdratio", "kdeath", "next", "load", "status", "servers",
                                     "top20", "top10", "top5", "clans", "bans", "cheaters", "statsme", "weapons",
                                     "weapon", "action", "actions", "accuracy", "targets", "target", "kills",
                                     "kill", "player_kills", "cmd", "cmds", "command", "hlx_display 0",
                                     "hlx_display 1", "hlx_teams 0", "hlx_teams 1", "hlx_hideranking",
                                     "hlx_chat 0", "hlx_chat 1", "hlx_menu", "servers 1", "servers 2",
                                     "servers 3", "hlx", "hlstatsx", "help" };
static const char menu_commands[][] = { "hlx_menu", "hlx", "hlstatsx" };

// blocked_commands as a set, checked on every chat message. The value is true for menu_commands.
StringMap g_hBlockedCommands;

Menu HLstatsXMenuMain;
Menu HLstatsXMenuAuto;
Menu HLstatsXMenuEvents;

int ColorSlotArray[] = { -1, -1, -1, -1, -1, -1 };

static const char ct_models[][] = {
	"models/player/ct_urban.mdl",
	"models/player/ct_gsg9.mdl",
	"models/player/ct_sas.mdl",
	"models/player/ct_gign.mdl"
};

static const char ts_models[][] = {
	"models/player/t_phoenix.mdl",
	"models/player/t_leet.mdl",
	"models/player/t_arctic.mdl",
	"models/player/t_guerilla.mdl"
};

static const char modnamelist[][] = {
	"Counter-Strike: Source",
	"Day of Defeat: Source",
	"Left 4 Dead (1 or 2)",
	"Team Fortress 2",
	"Half-Life 2 Deathmatch",
	"Insurgency",
	"Fortress Forever",
	"Zombie Panic: Source",
	"Age of Chivalry",
	"Fistful of Frags",
	"GoldenEye: Source",
	"Pirates, Vikings, and Knights",
	"CSPromod",
	"Nuclear Dawn",
	"Dino D-Day",
	"Counter-Strike: Global Offensive"
};

char message_prefix[32];
bool g_bPlyrCanDoMotd[MAXPLAYERS+1];
bool g_bGameCanDoMotd = true;
bool g_bTrackColors4Chat;
EngineVersion g_evEngineVersion = Engine_Unknown;
bool g_bTeamPlay;
bool g_bLateLoad = false;
bool g_bIgnoreNextTagChange = false;
ArrayList g_hCustomTags;
bool g_bProtobuf;
UserMsg g_umSayText = INVALID_MESSAGE_ID;
UserMsg g_umSayText2 = INVALID_MESSAGE_ID;

public Plugin myinfo = {
	name = "HLstatsX CE Ingame Plugin",
	author = "psychonic",
	description = "Provides ingame functionality for interaction from an HLstatsX CE installation",
	version = VERSION,
	url = "https://github.com/NiDE-gg/hlstatsx-community-edition"
};


public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	if (GetEngineVersion() == Engine_CSGO)
	{
		strcopy(error, err_max, "This plugin is not for CS:GO, please use hlstatsx-csgo plugin instead.");
		return APLRes_Failure;
	}

	g_bLateLoad = late;
	return APLRes_Success;
}


public void OnPluginStart()
{
	g_evEngineVersion = GetEngineVersion();
	g_bProtobuf = (GetUserMessageType() == UM_Protobuf);
	g_umSayText = GetUserMessageId("SayText");
	g_umSayText2 = GetUserMessageId("SayText2");

	get_server_mod();

	switch (gamemod)
	{
		case Game_CSS, Game_L4D, Game_TF, Game_HL2MP, Game_AOC, Game_FOF, Game_PVKII, Game_ND, Game_DDD:
		{
			g_bTrackColors4Chat = true;
			HookEvent("player_team", HLstatsX_Event_PlyTeamChange, EventHookMode_Pre);
		}
	}

	switch (gamemod)
	{
		case Game_L4D, Game_INSMOD, Game_GES:
		{
			g_bGameCanDoMotd = false;
		}
	}

	// The main menu depends on g_bGameCanDoMotd
	CreateHLstatsXMenus();

	g_hBlockedCommands = new StringMap();
	for (int i = 0; i < sizeof(blocked_commands); i++)
	{
		g_hBlockedCommands.SetValue(blocked_commands[i], false);
	}
	for (int i = 0; i < sizeof(menu_commands); i++)
	{
		g_hBlockedCommands.SetValue(menu_commands[i], true);
	}

	RegServerCmd("hlx_sm_psay",          hlx_sm_psay);
	RegServerCmd("hlx_sm_psay2",         hlx_sm_psay2);
	RegServerCmd("hlx_sm_bulkpsay",      hlx_sm_psay);
	RegServerCmd("hlx_sm_csay",          hlx_sm_csay);
	RegServerCmd("hlx_sm_msay",          hlx_sm_msay);
	RegServerCmd("hlx_sm_tsay",          hlx_sm_tsay);
	RegServerCmd("hlx_sm_hint",          hlx_sm_hint);
	RegServerCmd("hlx_sm_browse",        hlx_sm_browse);
	RegServerCmd("hlx_sm_swap",          hlx_sm_swap);
	RegServerCmd("hlx_sm_redirect",      hlx_sm_redirect);
	RegServerCmd("hlx_sm_player_action", hlx_sm_player_action);
	RegServerCmd("hlx_sm_team_action",   hlx_sm_team_action);
	RegServerCmd("hlx_sm_world_action",  hlx_sm_world_action);

	if (gamemod == Game_INSMOD)
	{
		AddCommandListener(hlx_block_commands, "say2");
	}
	else if (gamemod == Game_ND)
	{
		AddCommandListener(hlx_block_commands, "say_squad");
	}

	AddCommandListener(hlx_block_commands, "say");
	AddCommandListener(hlx_block_commands, "say_team");

	if (gamemod == Game_HL2MP)
	{
		ConVar teamplay = FindConVar("mp_teamplay");
		if (teamplay != null)
		{
			g_bTeamPlay = teamplay.BoolValue;
			teamplay.AddChangeHook(OnTeamPlayChange);
		}
	}

	CreateConVar("hlxce_plugin_version", VERSION, "HLstatsX:CE Ingame Plugin", FCVAR_NOTIFY);
	CreateConVar("hlxce_version", "", "HLstatsX:CE", FCVAR_NOTIFY);
	CreateConVar("hlxce_webpage", "http://www.hlxcommunity.com", "http://www.hlxcommunity.com", FCVAR_NOTIFY);

	hlx_block_chat_commands = CreateConVar("hlx_block_commands", "1", "If activated HLstatsX commands are blocked from the chat area");
	hlx_message_prefix = CreateConVar("hlx_message_prefix", "", "Define the prefix displayed on every HLstatsX ingame message");
	hlx_protect_address = CreateConVar("hlx_protect_address", "", "Address to be protected for logging/forwarding");
	hlx_server_tag = CreateConVar("hlx_server_tag", "1", "If enabled, adds \"HLstatsX:CE\" to server tags on supported games. 1 = Enabled (default), 0 = Disabled",
		_, true, 0.0, true, 1.0);
	hlx_block_status = CreateConVar("hlx_block_status", "0", "If enabled, blocks the status command when HLstatsX is installed. 1 = Enabled, 0 = Disabled (default)",
		_, true, 0.0, true, 1.0);

	g_hCustomTags = new ArrayList(ByteCountToCells(SVTAGSIZE));
	sv_tags = FindConVar("sv_tags");

	hlx_message_prefix.GetString(message_prefix, sizeof(message_prefix));

	if (g_bLateLoad)
	{
		char protaddr[64];
		hlx_protect_address.GetString(protaddr, sizeof(protaddr));
		OnProtectAddressChange(hlx_protect_address, "", protaddr);

		for (int client = 1; client <= MaxClients; client++)
		{
			if (IsClientInGame(client) && IsClientAuthorized(client))
			{
				OnClientPostAdminCheck(client);
			}
		}
	}

	MyAddServerTag(HLXTAG);

	hlx_message_prefix.AddChangeHook(OnMessagePrefixChange);
	hlx_protect_address.AddChangeHook(OnProtectAddressChange);
	hlx_server_tag.AddChangeHook(OnServerTagChange);
	if (sv_tags != null)
	{
		sv_tags.AddChangeHook(OnSVTagsChange);
	}

	RegServerCmd("log", ProtectLoggingChange);
	RegServerCmd("logaddress_del", ProtectForwardingChange);
	RegServerCmd("logaddress_delall", ProtectForwardingDelallChange);
	RegServerCmd("hlx_message_prefix_clear", MessagePrefixClear);

	GetTeams(gamemod == Game_INSMOD);
}


public void OnAllPluginsLoaded()
{
	if (LibraryExists("clientprefs"))
	{
		SetCookieMenuItem(HLXSettingsMenu, 0, "HLstatsX:CE Settings");
	}
}

public void HLXSettingsMenu(int client, CookieMenuAction action, any info, char[] buffer, int maxlen)
{
	if (action == CookieMenuAction_SelectOption)
	{
		HLstatsXMenuMain.Display(client, MENU_TIME_FOREVER);
	}
}


public void OnMapStart()
{
	GetTeams(gamemod == Game_INSMOD);

	if (g_bTrackColors4Chat)
	{
		find_player_team_slot(2);
		find_player_team_slot(3);
		if (gamemod == Game_PVKII)
		{
			find_player_team_slot(4);
		}
	}
}

bool BTagsSupported()
{
	return (sv_tags != null && (g_evEngineVersion == Engine_SourceSDK2007 || g_evEngineVersion == Engine_DODS || g_evEngineVersion == Engine_HL2DM || g_evEngineVersion == Engine_TF2 || gamemod == Game_ND));
}

void MyAddServerTag(const char[] tag)
{
	if (!BTagsSupported())
	{
		// game doesn't support sv_tags
		return;
	}

	if (!hlx_server_tag.BoolValue)
	{
		return;
	}

	if (g_hCustomTags.FindString(tag) == -1)
	{
		g_hCustomTags.PushString(tag);
	}

	char current_tags[SVTAGSIZE];
	sv_tags.GetString(current_tags, sizeof(current_tags));
	if (StrContains(current_tags, tag) > -1)
	{
		// already have tag
		return;
	}

	char new_tags[SVTAGSIZE];
	FormatEx(new_tags, sizeof(new_tags), "%s%s%s", current_tags, (current_tags[0] != '\0') ? "," : "", tag);
	SetServerTags(new_tags);
}

void MyRemoveServerTag(const char[] tag)
{
	if (!BTagsSupported())
	{
		// game doesn't support sv_tags
		return;
	}

	int idx = g_hCustomTags.FindString(tag);
	if (idx > -1)
	{
		g_hCustomTags.Erase(idx);
	}

	char current_tags[SVTAGSIZE];
	sv_tags.GetString(current_tags, sizeof(current_tags));
	if (StrContains(current_tags, tag) == -1)
	{
		// tag isn't on here, just bug out
		return;
	}

	ReplaceString(current_tags, sizeof(current_tags), tag, "");
	ReplaceString(current_tags, sizeof(current_tags), ",,", ",");

	// drop the comma left behind when the tag was first or last
	int len = strlen(current_tags);
	if (len > 0 && current_tags[len - 1] == ',')
	{
		current_tags[len - 1] = '\0';
	}
	SetServerTags(current_tags[(current_tags[0] == ',') ? 1 : 0]);
}

void SetServerTags(const char[] tags)
{
	// change sv_tags without a chat notice, and without OnSVTagsChange reapplying our tags
	int flags = sv_tags.Flags;
	sv_tags.Flags = flags & ~FCVAR_NOTIFY;
	g_bIgnoreNextTagChange = true;
	sv_tags.SetString(tags);
	g_bIgnoreNextTagChange = false;
	sv_tags.Flags = flags;
}

void get_server_mod()
{
	char game_description[64];
	GetGameDescription(game_description, sizeof(game_description), true);

	if (StrContains(game_description, "Counter-Strike", false) != -1)
	{
		gamemod = Game_CSS;
	}
	else if (StrContains(game_description, "Day of Defeat", false) != -1)
	{
		gamemod = Game_DODS;
	}
	else if (StrContains(game_description, "Half-Life 2 Deathmatch", false) != -1)
	{
		gamemod = Game_HL2MP;
	}
	else if (StrContains(game_description, "Team Fortress", false) != -1)
	{
		gamemod = Game_TF;
	}
	else if (StrContains(game_description, "L4D", false) != -1 || StrContains(game_description, "Left 4 D", false) != -1)
	{
		gamemod = Game_L4D;
	}
	else if (StrContains(game_description, "Insurgency", false) != -1)
	{
		gamemod = Game_INSMOD;
		//psychonic - added detection for more supported games
	}
	else if (StrContains(game_description, "Fortress Forever", false) != -1)
	{
		gamemod = Game_FF;
	}
	else if (StrContains(game_description, "ZPS", false) != -1)
	{
		gamemod = Game_ZPS;
	}
	else if (StrContains(game_description, "Age of Chivalry", false) != -1)
	{
		gamemod = Game_AOC;
	}
	else if (StrContains(game_description, "PVKII", false) != -1)
	{
		gamemod = Game_PVKII;
	}
	else if (StrContains(game_description, "CSPromod", false) != -1)
	{
		gamemod = Game_CSP;
	}
	else if (StrContains(game_description, "Nuclear Dawn", false) != -1)
	{
		gamemod = Game_ND;
	}

	// game mod could not detected, try further
	if (gamemod == Game_Unknown)
	{
		char game_folder[64];
		GetGameFolderName(game_folder, sizeof(game_folder));
		if (StrContains(game_folder, "cstrike", false) != -1)
		{
			gamemod = Game_CSS;
		}
		else if (strncmp(game_folder, "dod", 3, false) == 0)
		{
			gamemod = Game_DODS;
		}
		else if (StrContains(game_folder, "hl2mp", false) != -1 || StrContains(game_folder, "hl2ctf", false) != -1)
		{
			gamemod = Game_HL2MP;
		}
		else if (StrContains(game_folder, "fistful_of_frags", false) != -1)
		{
			gamemod = Game_FOF;
		}
		else if (strncmp(game_folder, "tf", 2, false) == 0)
		{
			gamemod = Game_TF;
		}
		else if (StrContains(game_folder, "left4dead", false) != -1)
		{
			gamemod = Game_L4D;
		}
		else if (StrContains(game_folder, "insurgency", false) != -1)
		{
			gamemod = Game_INSMOD;
			//psychonic - added detection for more supported games
		}
		else if (StrContains(game_folder, "FortressForever", false) != -1)
		{
			gamemod = Game_FF;
		}
		else if (StrContains(game_folder, "zps", false) != -1)
		{
			gamemod = Game_ZPS;
		}
		else if (StrContains(game_folder, "ageofchivalry", false) != -1)
		{
			gamemod = Game_AOC;
		}
		else if (StrContains(game_folder, "gesource", false) != -1)
		{
			gamemod = Game_GES;
		}
		else if (StrContains(game_folder, "pvkii", false) != -1)
		{
			gamemod = Game_PVKII;
		}
		else if (StrContains(game_folder, "cspromod", false) != -1)
		{
			gamemod = Game_CSP;
		}
		else if (StrContains(game_folder, "nucleardawn", false) != -1)
		{
			gamemod = Game_ND;
		}
		else if (StrContains(game_folder, "dinodday", false) != -1)
		{
			gamemod = Game_DDD;
		}
		else
		{
			LogToGame("HLX:CE Mod Not In Detected List, Using Defaults (%s, %s)", game_description, game_folder);
			LogToGame("HLX:CE If this is incorrect, please file a bug at https://github.com/NiDE-gg/hlstatsx-community-edition/issues");
		}
	}
	if (gamemod > Game_Unknown)
	{
		LogToGame("HLX:CE Mod Detection: %s", modnamelist[view_as<int>(gamemod)]);
		LogToGame("HLX:CE If this is incorrect, please file a bug at https://github.com/NiDE-gg/hlstatsx-community-edition/issues");
	}
}

public void OnClientPostAdminCheck(int client)
{
	if (g_bGameCanDoMotd && !IsFakeClient(client))
	{
		QueryClientConVar(client, "cl_disablehtmlmotd", motdQuery, GetClientUserId(client));
	}
}


public void motdQuery(QueryCookie cookie, int client, ConVarQueryResult result, const char[] cvarName, const char[] cvarValue, any userid)
{
	// the answer may arrive after the player left and someone else took the slot
	if (GetClientOfUserId(userid) == client)
	{
		g_bPlyrCanDoMotd[client] = (result != ConVarQuery_Okay || StringToInt(cvarValue) == 0);
	}
}


public void OnServerTagChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	if (hlx_server_tag.BoolValue)
	{
		MyAddServerTag(HLXTAG);
	}
	else
	{
		MyRemoveServerTag(HLXTAG);
	}
}

public void OnSVTagsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	if (g_bIgnoreNextTagChange)
	{
		// we fired this callback, no need to reapply tags
		return;
	}

	// reapply each custom tag
	char tag[SVTAGSIZE];
	int cnt = g_hCustomTags.Length;
	for (int i = 0; i < cnt; i++)
	{
		g_hCustomTags.GetString(i, tag, sizeof(tag));
		MyAddServerTag(tag);
	}
}


public void OnProtectAddressChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	if (newVal[0] != '\0')
	{
		LogToGame("Command: logaddress_add %s", newVal);
		ServerCommand("logaddress_add %s", newVal);
	}
}

public void OnTeamPlayChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	g_bTeamPlay = cvar.BoolValue;
}

public Action ProtectLoggingChange(int args)
{
	char protect_address[192];
	hlx_protect_address.GetString(protect_address, sizeof(protect_address));
	if (protect_address[0] != '\0' && args >= 1)
	{
		char log_action[192];
		GetCmdArg(1, log_action, sizeof(log_action));
		if (StrEqual(log_action, "off") || StrEqual(log_action, "0"))
		{
			LogToGame("HLstatsX address protection active, logging reenabled!");
			ServerCommand("log 1");
		}
	}
	return Plugin_Continue;
}


public Action ProtectForwardingChange(int args)
{
	char protect_address[192];
	hlx_protect_address.GetString(protect_address, sizeof(protect_address));
	if (protect_address[0] != '\0' && args >= 1)
	{
		// the tokenizer splits "ip:port" into several arguments, glue them back together
		char log_action[192];
		char temp_argument[192];
		for (int i = 1; i <= args; i++)
		{
			GetCmdArg(i, temp_argument, sizeof(temp_argument));
			StrCat(log_action, sizeof(log_action), temp_argument);
		}
		if (StrEqual(log_action, protect_address))
		{
			ReaddProtectedAddress(protect_address);
		}
	}
	return Plugin_Continue;
}


public Action ProtectForwardingDelallChange(int args)
{
	char protect_address[192];
	hlx_protect_address.GetString(protect_address, sizeof(protect_address));
	if (protect_address[0] != '\0')
	{
		ReaddProtectedAddress(protect_address);
	}
	return Plugin_Continue;
}


void ReaddProtectedAddress(const char[] protect_address)
{
	LogToGame("HLstatsX address protection active, logaddress readded!");
	ServerCommand("logaddress_add %s", protect_address);
}


public void OnMessagePrefixChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	strcopy(message_prefix, sizeof(message_prefix), newVal);
}


public Action MessagePrefixClear(int args)
{
	message_prefix[0] = '\0';
	return Plugin_Handled;
}


void find_player_team_slot(int team_index)
{
	ColorSlotArray[team_index] = -1;
	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && GetClientTeam(i) == team_index)
		{
			ColorSlotArray[team_index] = i;
			return;
		}
	}
}


void validate_team_colors()
{
	int last_team = (gamemod == Game_PVKII) ? 4 : 3;
	for (int team_index = 2; team_index <= last_team; team_index++)
	{
		int color_client = ColorSlotArray[team_index];
		if (color_client <= 0 || !IsClientInGame(color_client) || GetClientTeam(color_client) != team_index)
		{
			find_player_team_slot(team_index);
		}
	}
}


void clear_color_slot(int client)
{
	for (int i = 0; i < sizeof(ColorSlotArray); i++)
	{
		if (ColorSlotArray[i] == client)
		{
			ColorSlotArray[i] = -1;
		}
	}
}

public void OnClientDisconnect(int client)
{
	if (g_bTrackColors4Chat)
	{
		clear_color_slot(client);
	}

	g_bPlyrCanDoMotd[client] = false;
}

int color_player(int color_type, int player_index, char[] client_message, int maxlen)
{
	char client_name[MAX_NAME_LENGTH];
	if (client_message[0] == '\0' || !GetClientName(player_index, client_name, sizeof(client_name)) || client_name[0] == '\0')
	{
		return -1;
	}

	char colored_player_name[MAX_NAME_LENGTH + 8];
	if (gamemod == Game_FF)
	{
		int team = GetClientTeam(player_index);
		if (team > 1 && team < 6)
		{
			FormatEx(colored_player_name, sizeof(colored_player_name), "^%d%s^0", (team-1), client_name);
			if (ReplaceString(client_message, maxlen, client_name, colored_player_name) > 0)
			{
				return player_index;
			}
		}
		return -1;
	}

	char search_client_name[MAX_NAME_LENGTH + 2];
	if (color_type == 1)
	{
		FormatEx(search_client_name, sizeof(search_client_name), "%s ", client_name);
		switch (gamemod)
		{
			case Game_DODS, Game_GES, Game_CSP:
				FormatEx(colored_player_name, sizeof(colored_player_name), "\x04%s\x01 ", client_name);
			case Game_HL2MP:
				FormatEx(colored_player_name, sizeof(colored_player_name), "%c%s\x01 ", g_bTeamPlay?3:4, client_name);
			case Game_ZPS:
				FormatEx(colored_player_name, sizeof(colored_player_name), "\x05%s\x01 ", client_name);
			default:
				FormatEx(colored_player_name, sizeof(colored_player_name), "\x03%s\x01 ", client_name);
		}
		if (ReplaceString(client_message, maxlen, search_client_name, colored_player_name) > 0)
		{
			return player_index;
		}
	}
	else
	{
		FormatEx(search_client_name, sizeof(search_client_name), " %s ", client_name);
		switch (gamemod)
		{
			case Game_ZPS, Game_GES:
				FormatEx(colored_player_name, sizeof(colored_player_name), " \x05%s\x01 ", client_name);
			default:
				FormatEx(colored_player_name, sizeof(colored_player_name), " \x04%s\x01 ", client_name);
		}
		ReplaceString(client_message, maxlen, search_client_name, colored_player_name);
	}
	return -1;
}



int color_all_players(char[] message, int maxlen)
{
	if (message[0] == '\0'
		|| !(g_bTrackColors4Chat || (gamemod == Game_DODS) || (gamemod == Game_ZPS) || (gamemod == Game_FF) || (gamemod == Game_GES) || (gamemod == Game_CSP)))
	{
		return -1;
	}

	// Find every player named in the message before coloring anything: the one named
	// first gets the team color (a message can only carry one), the others a highlight.
	int matched_clients[MAXPLAYERS];
	int matched_count = 0;
	int lowest_matching_pos = maxlen;
	int lowest_matching_pos_client = -1;
	char client_name[MAX_NAME_LENGTH];

	for (int client = 1; client <= MaxClients; client++)
	{
		if (!IsClientInGame(client) || !GetClientName(client, client_name, sizeof(client_name)) || client_name[0] == '\0')
		{
			continue;
		}

		int message_pos = StrContains(message, client_name);
		if (message_pos > -1)
		{
			if (lowest_matching_pos > message_pos)
			{
				lowest_matching_pos = message_pos;
				lowest_matching_pos_client = client;
			}
			matched_clients[matched_count++] = client;
		}
	}

	int color_index = -1;
	for (int i = 0; i < matched_count; i++)
	{
		if (matched_clients[i] == lowest_matching_pos_client)
		{
			color_index = color_player(1, matched_clients[i], message, maxlen);
		}
		else
		{
			color_player(0, matched_clients[i], message, maxlen);
		}
	}

	return color_index;
}


// Replaces a team name with its colored version if a player of that team can lend his color
int color_team_name(char[] message, int maxlen, int team_index, const char[] search, const char[] replace)
{
	if (ColorSlotArray[team_index] > -1 && ReplaceString(message, maxlen, search, replace) > 0)
	{
		return ColorSlotArray[team_index];
	}
	return -1;
}


int color_team_entities(char[] message, int maxlen)
{
	if (message[0] == '\0')
	{
		return -1;
	}

	int color_index = -1;
	switch(gamemod)
	{
		case Game_CSS:
		{
			color_index = color_team_name(message, maxlen, 2, "TERRORIST ", "\x03TERRORIST\x01 ");
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 3, "CT ", "\x03CT\x01 ");
			}
		}
		case Game_L4D:
		{
			color_index = color_team_name(message, maxlen, 2, "Survivors ", "\x03Survivors\x01 ");
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 3, "Infected ", "\x03Infected\x01 ");
			}
		}
		case Game_TF:
		{
			color_index = color_team_name(message, maxlen, 2, "Red ", "\x03Red\x01 ");
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 3, "Blue ", "\x03Blue\x01 ");
			}
		}
		case Game_FF:
		{
			if (ReplaceString(message, maxlen, "Red Team", "^2Red Team^0") > 0
				|| ReplaceString(message, maxlen, "Blue Team", "^1Blue Team^0") > 0
				|| ReplaceString(message, maxlen, "Yellow Team", "^3Yellow Team^0") > 0
				|| ReplaceString(message, maxlen, "Green Team", "^4Green Team^0") > 0)
			{
				color_index = 0;
			}
		}
		case Game_AOC:
		{
			color_index = color_team_name(message, maxlen, 2, "Agathia Knights ", "\x03Agathia Knights\x01 ");
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 3, "The Mason Order ", "\x03The Mason Order\x01 ");
			}
		}
		case Game_FOF:
		{
			color_index = color_team_name(message, maxlen, 2, "Desperados ", "\x03Desperados\x01 ");
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 2, "Desparados ", "\x03Desperados\x01 ");
			}
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 3, "Vigilantes ", "\x03Vigilantes\x01 ");
			}
		}
		case Game_HL2MP:
		{
			if (g_bTeamPlay)
			{
				color_index = color_team_name(message, maxlen, 2, "The Combine ", "\x03The Combine\x01 ");
				if (color_index == -1)
				{
					color_index = color_team_name(message, maxlen, 3, "Rebel Forces ", "\x03Rebel Forces\x01 ");
				}
			}
		}
		case Game_PVKII:
		{
			color_index = color_team_name(message, maxlen, 2, "Pirates ", "\x03Pirates\x01 ");
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 3, "Vikings ", "\x03Vikings\x01 ");
			}
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 4, "Knights ", "\x03Knights\x01 ");
			}
		}
		case Game_ND:
		{
			color_index = color_team_name(message, maxlen, 2, "EMPIRE ", "\x03Empire\x01 ");
			if (color_index == -1)
			{
				color_index = color_team_name(message, maxlen, 3, "CONSORTIUM ", "\x03Consortium\x01 ");
			}
		}
		case Game_DDD:
		{
			if (g_bTeamPlay)
			{
				color_index = color_team_name(message, maxlen, 2, "Allies ", "\x03Allies\x01 ");
				if (color_index == -1)
				{
					color_index = color_team_name(message, maxlen, 3, "Axis ", "\x03Axis\x01 ");
				}
			}
		}
	}

	return color_index;
}


void display_menu(int player_index, int time, char[] full_message, int maxlen, bool need_handler)
{
	ReplaceString(full_message, maxlen, "\\n", "\n");
	if (!need_handler)
	{
		InternalShowMenu(player_index, full_message, time);
	}
	else
	{
		InternalShowMenu(player_index, full_message, time, (1<<0)|(1<<1)|(1<<2)|(1<<3)|(1<<4)|(1<<5)|(1<<6)|(1<<7)|(1<<8)|(1<<9), InternalMenuHandler);
	}
}


public int InternalMenuHandler(Menu menu, MenuAction action, int param1, int param2)
{
	int client = param1;
	if (IsClientInGame(client))
	{
		if (action == MenuAction_Select)
		{
			char player_event[16];
			IntToString(param2, player_event, sizeof(player_event));
			LogPlayerEvent(client, "selected", player_event);
		}
		else if (action == MenuAction_Cancel)
		{
			LogPlayerEvent(client, "selected", "cancel");
		}
	}
	return 0;
}


/**
 * Turns the comma separated userids sent by the daemon into in-game, human client indexes.
 *
 * @return              Number of clients written to clients[].
 */
int BuildClientList(const char[] client_list, int[] clients, int max_clients)
{
	char userids[MAXPLAYERS][12];
	int userid_count = ExplodeString(client_list, ",", userids, sizeof(userids), sizeof(userids[]));

	int count = 0;
	for (int i = 0; i < userid_count && count < max_clients; i++)
	{
		int client = GetClientOfUserId(StringToInt(userids[i]));
		if (client > 0 && IsClientInGame(client) && !IsFakeClient(client))
		{
			clients[count++] = client;
		}
	}
	return count;
}


/**
 * Reads the optional color flag the daemon sends before the message:
 * 0 = plain, 1 = color player and team names, 2 = no name coloring (sent to many players).
 *
 * @return              The flag, or -1 if this argument is already the message.
 */
int GetCmdColorFlag(int argnum)
{
	char flag[4];
	GetCmdArg(argnum, flag, sizeof(flag));
	if (flag[1] == '\0' && flag[0] >= '0' && flag[0] <= '2')
	{
		return flag[0] - '0';
	}
	return -1;
}


public Action hlx_sm_psay(int args)
{
	if (args < 2)
	{
		PrintToServer("Usage: hlx_sm_psay <userid><colored><message> - sends private message");
		return Plugin_Handled;
	}

	// sized for a full server: the default 192 cut the userid list on 64 slot servers
	char client_list[512];
	GetCmdArg(1, client_list, sizeof(client_list));

	int is_colored = GetCmdColorFlag(2);
	char client_message[192];
	GetCmdArg((is_colored == -1) ? 2 : 3, client_message, sizeof(client_message));
	if (is_colored == -1)
	{
		is_colored = 0;
	}

	int clients[MAXPLAYERS];
	int count = BuildClientList(client_list, clients, sizeof(clients));
	if (count == 0 || client_message[0] == '\0')
	{
		return Plugin_Handled;
	}

	char display_message[192];

	switch (gamemod)
	{
		case Game_CSS, Game_DODS, Game_L4D, Game_TF, Game_HL2MP, Game_ZPS, Game_AOC, Game_FOF, Game_GES, Game_PVKII, Game_CSP, Game_ND, Game_DDD:
		{
			int color_index = -1;
			if (is_colored == 1)
			{
				color_index = color_all_players(client_message, sizeof(client_message));
				if (color_index == -1)
				{
					if (g_bTrackColors4Chat)
					{
						validate_team_colors();
					}
					color_index = color_team_entities(client_message, sizeof(client_message));
				}
			}
			if (message_prefix[0] == '\0')
			{
				FormatEx(display_message, sizeof(display_message), "\x01\x0B\x01%s", client_message);
			}
			else
			{
				FormatEx(display_message, sizeof(display_message), "\x01\x0B%c%s\x01 %s", ((gamemod == Game_ZPS || gamemod == Game_GES)?5:4), message_prefix, client_message);
			}

			if (g_bTrackColors4Chat && is_colored != 2)
			{
				if (color_index != -1)
				{
					// same color for everyone: one message for all recipients
					SendSayText2(clients, count, color_index, display_message);
				}
				else
				{
					// nothing to color: each recipient sees his own team color
					int recipient[1];
					for (int i = 0; i < count; i++)
					{
						recipient[0] = clients[i];
						SendSayText2(recipient, 1, clients[i], display_message);
					}
				}
			}
			else
			{
				PrintToChatRecipients(clients, count, display_message);
			}
		}
		case Game_FF:
		{
			// thanks to hlstriker for help with this
			if (is_colored == 1)
			{
				if (color_all_players(client_message, sizeof(client_message)) == -1)
				{
					color_team_entities(client_message, sizeof(client_message));
				}
			}

			if (message_prefix[0] == '\0')
			{
				FormatEx(display_message, sizeof(display_message), "Console: %s%s\n", ((is_colored == 2)?"^4":""), client_message);
			}
			else
			{
				FormatEx(display_message, sizeof(display_message), "Console: ^4%s:%s %s\n", message_prefix, ((is_colored == 2)?"":"^"), client_message);
			}

			PrintToChatRecipientsFF(clients, count, display_message);
		}
		default:
		{
			if (message_prefix[0] != '\0')
			{
				FormatEx(display_message, sizeof(display_message), "%s %s", message_prefix, client_message);
				PrintToChatRecipients(clients, count, display_message);
			}
			else
			{
				PrintToChatRecipients(clients, count, client_message);
			}
		}
	}
	return Plugin_Handled;
}


public Action hlx_sm_psay2(int args)
{
	if (args < 2)
	{
		PrintToServer("Usage: hlx_sm_psay2 <userid><colored><message> - sends green colored private message");
		return Plugin_Handled;
	}

	char client_list[512];
	GetCmdArg(1, client_list, sizeof(client_list));

	char client_message[192];
	GetCmdArg((GetCmdColorFlag(2) == -1) ? 2 : 3, client_message, sizeof(client_message));

	int clients[MAXPLAYERS];
	int count = BuildClientList(client_list, clients, sizeof(clients));
	if (count == 0 || client_message[0] == '\0')
	{
		return Plugin_Handled;
	}

	// Strip color control codes
	char buffer_message[192];
	int j = 0;
	for (int i = 0; client_message[i] != '\0'; i++)
	{
		if (client_message[i] > 0 && client_message[i] < 5)
		{
			continue;
		}
		buffer_message[j++] = client_message[i];
	}
	buffer_message[j] = '\0';

	switch(gamemod)
	{
		case Game_INSMOD:
		{
			if (message_prefix[0] == '\0')
			{
				strcopy(client_message, sizeof(client_message), buffer_message);
			}
			else
			{
				FormatEx(client_message, sizeof(client_message), "%s: %s", message_prefix, buffer_message);
			}

			if (g_umSayText == INVALID_MESSAGE_ID)
			{
				return Plugin_Handled;
			}

			// thanks to Fyren and IceMatrix for help with this
			int recipient[1];
			for (int i = 0; i < count; i++)
			{
				int player_index = clients[i];
				recipient[0] = player_index;
				Handle hMsg = StartMessageEx(g_umSayText, recipient, 1, USERMSG_RELIABLE|USERMSG_BLOCKHOOKS);
				if (hMsg == null)
				{
					continue;
				}

				if (g_bProtobuf)
				{
					Protobuf pb = UserMessageToProtobuf(hMsg);
					pb.SetInt("ent_idx", player_index);
					pb.SetBool("chat", true);
					pb.SetString("text", client_message);
				}
				else
				{
					BfWrite bf = UserMessageToBfWrite(hMsg);
					bf.WriteByte(1);
					bf.WriteBool(true);
					bf.WriteByte(player_index);
					bf.WriteString(client_message);
				}

				EndMessage();
			}
		}
		case Game_FF:
		{
			if (message_prefix[0] == '\0')
			{
				FormatEx(client_message, sizeof(client_message), "Console: \x02^4%s\n", buffer_message);
			}
			else
			{
				FormatEx(client_message, sizeof(client_message), "Console: \x02^4%s: %s\n", message_prefix, buffer_message);
			}

			PrintToChatRecipientsFF(clients, count, client_message);
		}
		case Game_ZPS, Game_GES:
		{
			if (message_prefix[0] == '\0')
			{
				FormatEx(client_message, sizeof(client_message), "\x05%s", buffer_message);
			}
			else
			{
				FormatEx(client_message, sizeof(client_message), "\x05%s %s", message_prefix, buffer_message);
			}
			PrintToChatRecipients(clients, count, client_message);
		}
		default:
		{
			if (message_prefix[0] == '\0')
			{
				FormatEx(client_message, sizeof(client_message), "\x04%s", buffer_message);
			}
			else
			{
				FormatEx(client_message, sizeof(client_message), "\x04%s %s", message_prefix, buffer_message);
			}
			PrintToChatRecipients(clients, count, client_message);
		}
	}
	return Plugin_Handled;
}


public Action hlx_sm_csay(int args)
{
	if (args < 1)
	{
		PrintToServer("Usage: hlx_sm_csay <message> - display center message");
		return Plugin_Handled;
	}

	char display_message[192];
	GetCmdArg(1, display_message, sizeof(display_message));

	if (display_message[0] != '\0')
	{
		if (gamemod == Game_L4D)
		{
			PrintToChatAll("\x03%s", display_message);
		}
		else
		{
			PrintCenterTextAll("%s", display_message);
		}
	}

	return Plugin_Handled;
}


public Action hlx_sm_msay(int args)
{
	if (args < 3)
	{
		PrintToServer("Usage: hlx_sm_msay <time><userid><message> - sends hud message");
		return Plugin_Handled;
	}

	if (gamemod == Game_HL2MP)
	{
		return Plugin_Handled;
	}

	char display_time[16];
	GetCmdArg(1, display_time, sizeof(display_time));

	char client_id[32];
	GetCmdArg(2, client_id, sizeof(client_id));

	// optional "1" (log the selected key back to the daemon) or "0" before the message
	char handler_param[32];
	GetCmdArg(3, handler_param, sizeof(handler_param));

	int ignore_param = 0;
	bool need_handler = false;
	if (handler_param[1] == '\0' && (handler_param[0] == '1' || handler_param[0] == '0'))
	{
		need_handler = (handler_param[0] == '1');
		ignore_param = 1;
	}

	char client_message[1024];
	GetCmdArg((ignore_param + 3), client_message, sizeof(client_message));

	int time = StringToInt(display_time);
	if (time <= 0)
	{
		time = 10;
	}

	int client = StringToInt(client_id);
	if (client > 0)
	{
		int player_index = GetClientOfUserId(client);
		if (player_index > 0 && IsClientInGame(player_index) && !IsFakeClient(player_index) && client_message[0] != '\0')
		{
			display_menu(player_index, time, client_message, sizeof(client_message), need_handler);
		}
	}

	return Plugin_Handled;
}


public Action hlx_sm_tsay(int args)
{
	if (args < 3)
	{
		PrintToServer("Usage: hlx_sm_tsay <time><userid><message> - sends hud message");
		return Plugin_Handled;
	}

	char display_time[16];
	GetCmdArg(1, display_time, sizeof(display_time));

	char client_id[32];
	GetCmdArg(2, client_id, sizeof(client_id));

	char client_message[192];
	GetCmdArg(3, client_message, sizeof(client_message));

	int client = StringToInt(client_id);
	if ((client > 0) && (client_message[0] != '\0'))
	{
		int player_index = GetClientOfUserId(client);
		if (player_index > 0 && IsClientInGame(player_index) && !IsFakeClient(player_index))
		{
			KeyValues values = new KeyValues("msg");
			values.SetString("title", client_message);
			values.SetNum("level", 1);
			values.SetString("time", display_time);
			CreateDialog(player_index, values, DialogType_Msg);
			delete values;
		}
	}

	return Plugin_Handled;
}


public Action hlx_sm_hint(int args)
{
	if (args < 2)
	{
		PrintToServer("Usage: hlx_sm_hint <userid><message> - send hint message");
		return Plugin_Handled;
	}

	char client_list[512];
	GetCmdArg(1, client_list, sizeof(client_list));

	char client_message[192];
	GetCmdArg(2, client_message, sizeof(client_message));
	if (client_message[0] == '\0')
	{
		return Plugin_Handled;
	}

	int clients[MAXPLAYERS];
	int count = BuildClientList(client_list, clients, sizeof(clients));
	for (int i = 0; i < count; i++)
	{
		PrintHintText(clients[i], "%s", client_message);
	}
	return Plugin_Handled;
}


public Action hlx_sm_browse(int args)
{
	if (args < 2)
	{
		PrintToServer("Usage: hlx_sm_browse <userid><url> - open client ingame browser");
		return Plugin_Handled;
	}

	char client_list[512];
	GetCmdArg(1, client_list, sizeof(client_list));

	char client_url[192];
	GetCmdArg(2, client_url, sizeof(client_url));
	if (client_url[0] == '\0')
	{
		return Plugin_Handled;
	}

	int clients[MAXPLAYERS];
	int count = BuildClientList(client_list, clients, sizeof(clients));
	for (int i = 0; i < count; i++)
	{
		int player_index = clients[i];
		if (!g_bGameCanDoMotd)
		{
			PrintToChat(player_index, "This game does not support the HTML MOTD window required for this command");
		}
		else if (!g_bPlyrCanDoMotd[player_index])
		{
			PrintToChat(player_index, "HTML MOTD needs to be enabled in your game options to use this command");
		}
		else
		{
			// ShowMOTDPanel handles both bitbuf and protobuf games
			ShowMOTDPanel(player_index, "HLstatsX:CE", client_url, MOTDPANEL_TYPE_URL);
		}
	}

	return Plugin_Handled;
}


public Action hlx_sm_swap(int args)
{
	if (args < 1)
	{
		PrintToServer("Usage: hlx_sm_swap <userid> - swaps players to the opposite team (css only)");
		return Plugin_Handled;
	}

	if (gamemod != Game_CSS)
	{
		PrintToServer("hlx_sm_swap is not supported by this game.");
		return Plugin_Handled;
	}

	char client_id[32];
	GetCmdArg(1, client_id, sizeof(client_id));

	int client = StringToInt(client_id);
	if (client > 0)
	{
		int player_index = GetClientOfUserId(client);
		if (player_index > 0 && IsClientInGame(player_index))
		{
			swap_player(player_index);
		}
	}
	return Plugin_Handled;
}


public Action hlx_sm_redirect(int args)
{
	if (args < 3)
	{
		PrintToServer("Usage: hlx_sm_redirect <time><userid><address><reason> - asks player to be redirected to specified gameserver");
		return Plugin_Handled;
	}

	char display_time[16];
	GetCmdArg(1, display_time, sizeof(display_time));

	char client_list[512];
	GetCmdArg(2, client_list, sizeof(client_list));

	char server_address[192];
	GetCmdArg(3, server_address, sizeof(server_address));
	if (server_address[0] == '\0')
	{
		return Plugin_Handled;
	}

	char redirect_reason[192];
	GetCmdArg(4, redirect_reason, sizeof(redirect_reason));

	float display_time_float = StringToFloat(display_time);

	int clients[MAXPLAYERS];
	int count = BuildClientList(client_list, clients, sizeof(clients));
	for (int i = 0; i < count; i++)
	{
		KeyValues top_values = new KeyValues("msg");
		top_values.SetString("title", redirect_reason);
		top_values.SetNum("level", 1);
		top_values.SetString("time", display_time);
		CreateDialog(clients[i], top_values, DialogType_Msg);
		delete top_values;

		DisplayAskConnectBox(clients[i], display_time_float, server_address);
	}

	return Plugin_Handled;
}



public Action hlx_sm_player_action(int args)
{
	if (args < 2)
	{
		PrintToServer("Usage: hlx_sm_player_action <clientid><action> - trigger player action to be handled from HLstatsX");
		return Plugin_Handled;
	}

	char client_id[32];
	GetCmdArg(1, client_id, sizeof(client_id));

	char player_action[64];
	GetCmdArg(2, player_action, sizeof(player_action));

	int client = StringToInt(client_id);

	LogPlayerEvent(client, "triggered", player_action);

	return Plugin_Handled;
}


public Action hlx_sm_team_action(int args)
{
	if (args < 2)
	{
		PrintToServer("Usage: hlx_sm_team_action <team_name><action> - trigger team action to be handled from HLstatsX");
		return Plugin_Handled;
	}

	char team_name[64];
	GetCmdArg(1, team_name, sizeof(team_name));

	char team_action[64];
	GetCmdArg(2, team_action, sizeof(team_action));

	LogToGame("Team \"%s\" triggered \"%s\"", team_name, team_action);

	return Plugin_Handled;
}


public Action hlx_sm_world_action(int args)
{
	if (args < 1)
	{
		PrintToServer("Usage: hlx_sm_world_action <action> - trigger world action to be handled from HLstatsX");
		return Plugin_Handled;
	}

	char world_action[64];
	GetCmdArg(1, world_action, sizeof(world_action));

	LogToGame("World triggered \"%s\"", world_action);

	return Plugin_Handled;
}


public Action hlx_block_commands(int client, const char[] command, int argc)
{
	if (client == 0)
	{
		return Plugin_Continue;
	}

	char user_command[192];
	GetCmdArgString(user_command, sizeof(user_command));

	int command_length = strlen(user_command);
	if (command_length == 0)
	{
		return Plugin_Continue;
	}

	int start_index = 0;
	if (user_command[0] == '"')
	{
		start_index = 1;
		if (user_command[command_length - 1] == '"')
		{
			user_command[command_length - 1] = '\0';
		}
	}

	// the text as typed (with its "/"), which is what the daemon gets
	int origin_index = start_index;
	if (user_command[start_index] == '/')
	{
		start_index++;
	}

	if (hlx_block_status.BoolValue && StrEqual(user_command[origin_index], "status", false))
	{
		return Plugin_Stop;
	}

	char command_type[16] = "say";

	if (gamemod == Game_INSMOD)
	{
		// Insurgency prefixes the text with the chat type: "1" for all, "2" for team
		if (user_command[start_index] == '2')
		{
			command_type = "say_team";
		}
		start_index += 4;
	}

	bool is_menu_command;
	bool is_hlx_command = g_hBlockedCommands.GetValue(user_command[start_index], is_menu_command);

	if (hlx_block_chat_commands.IntValue <= 0)
	{
		if (is_hlx_command && is_menu_command && IsClientInGame(client))
		{
			HLstatsXMenuMain.Display(client, MENU_TIME_FOREVER);
		}
		return Plugin_Continue;
	}

	if (!is_hlx_command)
	{
		return Plugin_Continue;
	}

	if (IsClientInGame(client))
	{
		if (is_menu_command)
		{
			HLstatsXMenuMain.Display(client, MENU_TIME_FOREVER);
		}

		if (gamemod == Game_INSMOD)
		{
			LogPlayerEvent(client, command_type, user_command[start_index]);
		}
		else
		{
			LogPlayerEvent(client, command_type, user_command[origin_index]);
		}
	}
	return Plugin_Stop;
}


public void HLstatsX_Event_PlyTeamChange(Event event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId(event.GetInt("userid"));
	if (client > 0)
	{
		clear_color_slot(client);
	}
}



void swap_player(int player_index)
{
	bool was_alive = IsPlayerAlive(player_index);
	switch (GetClientTeam(player_index))
	{
		case CS_TEAM_CT:
		{
			CS_SwitchTeam(player_index, CS_TEAM_T);
			if (was_alive)
			{
				CS_RespawnPlayer(player_index);
				SetEntityModel(player_index, ts_models[GetRandomInt(0, sizeof(ts_models) - 1)]);
			}
		}
		case CS_TEAM_T:
		{
			CS_SwitchTeam(player_index, CS_TEAM_CT);
			if (was_alive)
			{
				CS_RespawnPlayer(player_index);
				SetEntityModel(player_index, ct_models[GetRandomInt(0, sizeof(ct_models) - 1)]);
				int weapon_entity = GetPlayerWeaponSlot(player_index, CS_SLOT_C4);
				if (weapon_entity > 0)
				{
					char class_name[32];
					GetEntityClassname(weapon_entity, class_name, sizeof(class_name));
					if (StrEqual(class_name, "weapon_c4"))
					{
						RemovePlayerItem(player_index, weapon_entity);
						RemoveEntity(weapon_entity);
					}
				}
			}
		}
	}
}


void CreateHLstatsXMenus()
{
	// Each item holds the chat command sent to the daemon, or the submenu it opens
	HLstatsXMenuMain = new Menu(HLstatsXMenuHandler);
	HLstatsXMenuMain.SetTitle("HLstatsX - Main Menu");
	HLstatsXMenuMain.AddItem("/rank", "Display Rank");
	HLstatsXMenuMain.AddItem("/next", "Next Players");
	HLstatsXMenuMain.AddItem("/top10", "Top10 Players");
	if (g_bGameCanDoMotd)
	{
		HLstatsXMenuMain.AddItem("/clans", "Clans Ranking");
		HLstatsXMenuMain.AddItem("/status", "Server Status");
		HLstatsXMenuMain.AddItem("/statsme", "Statsme");
	}
	HLstatsXMenuMain.AddItem(MENU_AUTO, "Auto Ranking");
	HLstatsXMenuMain.AddItem(MENU_EVENTS, "Toggle Point Msgs");
	if (g_bGameCanDoMotd)
	{
		HLstatsXMenuMain.AddItem("/weapons", "Weapon Usage");
		HLstatsXMenuMain.AddItem("/accuracy", "Weapons Accuracy");
		HLstatsXMenuMain.AddItem("/targets", "Weapons Targets");
		HLstatsXMenuMain.AddItem("/kills", "Player Kills");
	}
	HLstatsXMenuMain.AddItem("/hlx_hideranking", "Toggle Ranking Display");
	if (g_bGameCanDoMotd)
	{
		HLstatsXMenuMain.AddItem("/bans", "Ban and Cheater List");
		HLstatsXMenuMain.AddItem("/help", "Display Help");
	}
	HLstatsXMenuMain.Pagination = 8;

	HLstatsXMenuAuto = new Menu(HLstatsXMenuHandler);
	HLstatsXMenuAuto.SetTitle("HLstatsX - Auto-Ranking");
	HLstatsXMenuAuto.AddItem("/hlx_auto start rank", "Enable on round-start");
	HLstatsXMenuAuto.AddItem("/hlx_auto end rank", "Enable on round-end");
	HLstatsXMenuAuto.AddItem("/hlx_auto kill rank", "Enable on player death");
	HLstatsXMenuAuto.AddItem("/hlx_auto clear", "Disable");
	HLstatsXMenuAuto.Pagination = 8;

	HLstatsXMenuEvents = new Menu(HLstatsXMenuHandler);
	HLstatsXMenuEvents.SetTitle("HLstatsX - Console Events");
	HLstatsXMenuEvents.AddItem("/hlx_display 1", "Enable Events");
	HLstatsXMenuEvents.AddItem("/hlx_display 0", "Disable Events");
	HLstatsXMenuEvents.AddItem("/hlx_chat 1", "Enable Global Chat");
	HLstatsXMenuEvents.AddItem("/hlx_chat 0", "Disable Global Chat");
	HLstatsXMenuEvents.Pagination = 8;
}


public int HLstatsXMenuHandler(Menu menu, MenuAction action, int param1, int param2)
{
	if (action == MenuAction_Select && IsClientInGame(param1))
	{
		char player_command[32];
		menu.GetItem(param2, player_command, sizeof(player_command));

		if (StrEqual(player_command, MENU_AUTO))
		{
			HLstatsXMenuAuto.Display(param1, MENU_TIME_FOREVER);
		}
		else if (StrEqual(player_command, MENU_EVENTS))
		{
			HLstatsXMenuEvents.Display(param1, MENU_TIME_FOREVER);
		}
		else
		{
			LogPlayerEvent(param1, "say", player_command);
		}
	}
	return 0;
}


void PrintToChatRecipients(const int[] clients, int count, const char[] message)
{
	for (int i = 0; i < count; i++)
	{
		PrintToChat(clients[i], "%s", message);
	}
}


// SayText2 lends the author's team color to \x03
void SendSayText2(const int[] clients, int count, int author, const char[] message)
{
	if (g_umSayText2 == INVALID_MESSAGE_ID)
	{
		return;
	}

	Handle hMsg = StartMessageEx(g_umSayText2, clients, count, USERMSG_RELIABLE|USERMSG_BLOCKHOOKS);
	if (hMsg == null)
	{
		return;
	}

	if (g_bProtobuf)
	{
		Protobuf pb = UserMessageToProtobuf(hMsg);
		pb.SetInt("ent_idx", 0);
		pb.SetBool("chat", false);
		pb.SetString("msg_name", message);
		pb.AddString("params", "");
		pb.AddString("params", "");
		pb.AddString("params", "");
		pb.AddString("params", "");
	}
	else
	{
		BfWrite bf = UserMessageToBfWrite(hMsg);
		bf.WriteByte(author);
		bf.WriteByte(0);
		bf.WriteString(message);
	}

	EndMessage();
}


void PrintToChatRecipientsFF(const int[] clients, int count, const char[] message)
{
	if (g_umSayText == INVALID_MESSAGE_ID)
	{
		return;
	}

	Handle hMsg = StartMessageEx(g_umSayText, clients, count, USERMSG_RELIABLE|USERMSG_BLOCKHOOKS);
	if (hMsg == null)
	{
		return;
	}

	if (g_bProtobuf)
	{
		Protobuf pb = UserMessageToProtobuf(hMsg);
		pb.SetInt("ent_idx", 0);
		pb.SetBool("chat", true);
		pb.SetString("text", message);
	}
	else
	{
		BfWrite bf = UserMessageToBfWrite(hMsg);
		bf.WriteByte(0); // send as console
		bf.WriteString(message);
		bf.WriteByte(1); // 1 to enable color parsing, 0 to not
	}
	EndMessage();
}
