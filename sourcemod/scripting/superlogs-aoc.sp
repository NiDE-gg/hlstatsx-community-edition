/**
 * HLstatsX Community Edition - SourceMod plugin to generate advanced weapon logging
 * http://www.hlxcommunity.com
 * Copyright (C) 2009 Nicholas Hastings (psychonic)
 * Copyright (C) 2007-2008 TTS Oetzel & Goerz GmbH
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

#define NAME "SuperLogs: Age of Chivalry"
#define VERSION "1.0.4"

ConVar g_cvar_headshots = null;
ConVar g_cvar_locations = null;
ConVar g_cvar_classchanges = null;
ConVar g_cvar_actions = null;

bool g_logheadshots = true;
bool g_loglocations = true;
bool g_logclasschanges = true;
bool g_logactions = true;

bool g_bLogClassNextSpawn[MAXPLAYERS+1];

#include <loghelper>

public Plugin myinfo = {
	name = NAME,
	author = "psychonic",
	description = "Advanced logging for Age of Chivalry. Generates auxilary logging for use with log parsers such as HLstatsX and Psychostats",
	version = VERSION,
	url = "http://www.hlxcommunity.com"
};


public void OnPluginStart()
{
	g_cvar_locations = CreateConVar("superlogs_locations", "1", "Enable logging of kill coordinates (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_headshots = CreateConVar("superlogs_headshots", "1", "Enable logging of headshot and decapitation actions (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_classchanges = CreateConVar("superlogs_classchanges", "1", "Enable logging of character changes (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_actions = CreateConVar("superlogs_actions", "1", "Enable logging of actions, such as \"Round_Win\" (default on)", 0, true, 0.0, true, 1.0);

	g_cvar_locations.AddChangeHook(OnCvarLocationsChange);
	g_cvar_headshots.AddChangeHook(OnCvarHeadshotsChange);
	g_cvar_classchanges.AddChangeHook(OnCvarClasschangesChange);
	g_cvar_actions.AddChangeHook(OnCvarActionsChange);

	CreateConVar("superlogs_aoc_version", VERSION, NAME, FCVAR_SPONLY|FCVAR_REPLICATED|FCVAR_NOTIFY);
		
	HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
	HookEvent("round_end", Event_RoundEnd);
	hook_classchanges();
	
	CreateTimer(1.0, LogMap);
	
	GetTeams();

	// apply the cvar values they kept if the plugin was reloaded
	OnCvarLocationsChange(g_cvar_locations, "", "");
	OnCvarHeadshotsChange(g_cvar_headshots, "", "");
	OnCvarClasschangesChange(g_cvar_classchanges, "", "");
	OnCvarActionsChange(g_cvar_actions, "", "");
}

void hook_classchanges()
{
	HookUserMessage(GetUserMessageId("ClassChanged"), Event_ClassChanged);
	HookEvent("player_spawn", Event_Spawn, EventHookMode_Pre);
	
	for (int i = 1; i <= MAXPLAYERS; i++)
	{
		g_bLogClassNextSpawn[i] = false;
	}
}

void unhook_classchanges()
{
	UnhookUserMessage(GetUserMessageId("ClassChanged"), Event_ClassChanged);
	UnhookEvent("player_spawn", Event_Spawn, EventHookMode_Pre);
}


public void OnMapStart()
{
	GetTeams();
}

public Action Event_ClassChanged(UserMsg msg_id, BfRead bf, const int[] players, int playersNum, bool reliable, bool init)
{
	g_bLogClassNextSpawn[players[0]] = true;
	return Plugin_Continue;
}

public Action Event_Spawn(Event event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId(event.GetInt("userid"));
	if (client > 0 && g_bLogClassNextSpawn[client])
	{
		switch (GetEntProp(client, Prop_Send, "m_iClass"))
		{
			case 0:
				LogRoleChange(client, "Longbowman");
			case 1:
				LogRoleChange(client, "Crossbowman");
			case 2:
				LogRoleChange(client, "Javelineer");
			case 3:
				LogRoleChange(client, "Man at Arms");
			case 4:
				LogRoleChange(client, "Sergeant");
			case 5:
				LogRoleChange(client, "Guardsman");
			case 6:
				LogRoleChange(client, "Crusader");
			case 7:
				LogRoleChange(client, "Knight");
			case 8:
				LogRoleChange(client, "Heavy Knight");
		}
		g_bLogClassNextSpawn[client] = false;
	}
	
	return Plugin_Continue;
}


public Action Event_PlayerDeathPre(Event event, const char[] name, bool dontBroadcast)
{
	//	"userid"	"short"   	// user ID who died				
	//	"attacker"	"short"	 	// user ID who killed
	//	"weapon"	"string" 	// weapon name killed used
	//	"printweapon"	"string" 	// full print weapon name killed used
	//	"hitgroup"	"long"		// Part of body that was hit
	//	"damagetype" "long"		// CDamageInfo.GetAOCDamageType()
	//	"weaponid"	"short"		// Weapon ID
	//	"team"		"short"		// victim's team
	
	int attacker = event.GetInt("attacker");
	int victim = event.GetInt("userid");
	
	if (attacker > 0 && victim > 0)
	{
		if (g_loglocations)
		{
			LogKillLoc(GetClientOfUserId(attacker), GetClientOfUserId(victim));
		}
		
		if (g_logheadshots)
		{
			bool headshot = event.GetBool("headshot");
			bool decapped = event.GetBool("decapped");
			bool headexplodie = event.GetBool("headexplodie");
			if (decapped)
			{
				//decapitation
				LogPlayerEvent(GetClientOfUserId(attacker), "triggered", "headshot", true, " (hstype \"decap\")");
			}
			else if (headshot)
			{
				//headshot
				LogPlayerEvent(GetClientOfUserId(attacker), "triggered", "headshot", true, " (hstype \"headshot\")");
			}
			else if (headexplodie)
			{
				// head "explodie"
				LogPlayerEvent(GetClientOfUserId(attacker), "triggered", "headshot", true, " (hstype \"headexplodie\")");
			}
		}
	}
	
	return Plugin_Continue;
}

public void OnClientPutInServer(int client)
{
	g_bLogClassNextSpawn[client] = false;
}

public void Event_RoundEnd(Event event, const char[] name, bool dontBroadcast)
{
	LogTeamEvent(event.GetInt("winner"), "triggered", "Round_Win");
}

public void OnCvarLocationsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_loglocations;
	g_loglocations = g_cvar_locations.BoolValue;
	
	if (old_value != g_loglocations)
	{
		if (g_loglocations & !g_logheadshots)
		{
			HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
		else if (!g_logheadshots)
		{
			UnhookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
	}
}

public void OnCvarActionsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logactions;
	g_logactions = g_cvar_actions.BoolValue;
	
	if (old_value != g_logactions)
	{
		if (g_logactions)
		{
			HookEvent("round_end", Event_RoundEnd);
		}
		else
		{
			UnhookEvent("round_end", Event_RoundEnd);
		}
	}
}

public void OnCvarHeadshotsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logheadshots;
	g_logheadshots = g_cvar_headshots.BoolValue;
	
	if (old_value != g_logheadshots)
	{
		if (g_logheadshots & !g_loglocations)
		{
			HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
		else if (!g_loglocations)
		{
			UnhookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
	}
}

public void OnCvarClasschangesChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logclasschanges;
	g_logclasschanges = g_cvar_classchanges.BoolValue;
	
	if (old_value != g_logclasschanges)
	{
		if (g_logclasschanges)
		{
			hook_classchanges();
		}
		else
		{
			unhook_classchanges();
		}
	}
}

public Action LogMap(Handle timer)
{
	// Called 1 second after OnPluginStart since srcds does not log the first map loaded. Idea from Stormtrooper's "mapfix.sp" for psychostats
	LogMapLoad();
	return Plugin_Stop;
}