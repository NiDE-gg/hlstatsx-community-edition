#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>

#undef REQUIRE_PLUGIN
#undef REQUIRE_EXTENSIONS
#include <hlstatsx_api>
#define REQUIRE_EXTENSIONS
#define REQUIRE_PLUGIN

#define PLUGIN_VERSION "1.0.0"

bool g_bHLStatsApi;

public Plugin myinfo = 
{
	name = "HLStatsX API Test Plugin",
	author = "NomisCZ (-N-)",
	description = "HLStatsX API test plugin",
	version = PLUGIN_VERSION,
	url = "https://github.com/NomisCZ/hlstatsx-community-edition"
};

public void OnPluginStart()
{
	RegAdminCmd("sm_stats_test", Command_ApiGetTest, ADMFLAG_ROOT);
}

public void OnAllPluginsLoaded()
{	
	g_bHLStatsApi = LibraryExists("hlstatsx_api");
}

public void OnLibraryAdded(const char[] name)
{
	if (StrEqual(name, "hlstatsx_api")) {
		g_bHLStatsApi = true;
	}
}

public void OnLibraryRemoved(const char[] name)
{
	if (StrEqual(name, "hlstatsx_api")) {
		g_bHLStatsApi = false;
	}
}

public Action Command_ApiGetTest(int client, int args)
{
	if (!g_bHLStatsApi) {

		PrintToConsole(client, "HLStatsX API plugin is not loaded!");
		return Plugin_Handled;
	}

	bool request = HLStatsX_Api_GetStats("playerinfo", client, _GetTest_Response, 0);

	if (request) {
		PrintToConsole(client, "[DONE] API request: GetStats > %N", client);
	} else {
		PrintToConsole(client, "[FAILED] API request: GetStats > %N", client);
	}

	return Plugin_Handled;
}

public void _GetTest_Response(int command, int payload, int client, DataPack &datapack)
{
	if (IsValidClient(client)) {

		// https://sm.alliedmods.net/new-api/datapack/DataPack
		// The pack belongs to the API plugin, which frees it when this callback returns: read it
		// here and never delete it (CloneHandle it to keep the data longer).

		// You can also set position eg. to 5 and get values from that position - kd and others
		// datapack.Position = view_as<DataPackPos>(5);

		int rank = datapack.ReadCell();
		int skill = datapack.ReadCell();
		int kills = datapack.ReadCell();
		int deaths = datapack.ReadCell();
		float kd = datapack.ReadFloat();

		PrintToConsole(client, "[DONE] API response: Stats > %N (%i): %i, %i, %i, %i, %f", client, client, rank, skill, kills, deaths, kd);
	}
}

/**
* Check if for a valid client
*
*
* @param client				Client Index
* @param allowDead			Allow Dead players?
* @param allowBots			Allow Bots?
* @noreturn
*/
bool IsValidClient(int client, bool allowDead = true, bool allowBots = false)
{
	if (!(1 <= client <= MaxClients) || !IsClientInGame(client) || (IsFakeClient(client) && !allowBots) || IsClientSourceTV(client) || IsClientReplay(client) || (!allowDead && !IsPlayerAlive(client))) {
		return false;
	}
	return true;
}