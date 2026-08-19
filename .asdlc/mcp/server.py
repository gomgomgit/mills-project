from mcp.server.fastmcp import FastMCP
from lib.tools import artifact, dep_graph

# Create the MCP server instance.
# All tools are registered via module-level register() calls below.
mcp = FastMCP("asdlc")

# Registers: artifact__list, artifact__read, artifact__write, artifact__read_scheme
artifact.register(mcp)

# Registers: dep_graph__track_node, dep_graph__sync_stale_status, dep_graph__get_stale_nodes
dep_graph.register(mcp)

if __name__ == "__main__":
    mcp.run()
