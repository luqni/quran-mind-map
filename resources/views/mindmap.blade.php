<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quran Mind Map</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- D3.js -->
    <script src="https://d3js.org/d3.v7.min.js"></script>
<style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .node rect {
            fill: #fff;
            stroke: #10B981; /* emerald-500 */
            stroke-width: 2px;
            rx: 8;
            ry: 8;
            cursor: pointer;
            transition: all 0.3s;
            filter: drop-shadow(0 4px 3px rgb(0 0 0 / 0.07)) drop-shadow(0 2px 2px rgb(0 0 0 / 0.06));
        }
        .node rect:hover {
            stroke-width: 3px;
            stroke: #059669; /* emerald-600 */
            fill: #f0fdf4; /* emerald-50 */
        }
        .node text {
            font: 14px 'Inter', sans-serif;
            fill: #1e293b; /* slate-800 */
            pointer-events: none;
            dominant-baseline: middle;
            white-space: nowrap; /* Prevent text wrapping */
        }
        .node .ayah-range {
            font-size: 11px;
            fill: #64748b; /* slate-500 */
            font-weight: 500;
        }
        .node .node-label {
            font-weight: 600;
        }
        .link {
            fill: none;
            stroke: #e2e8f0; /* slate-200 */
            stroke-width: 2px;
        }
        
        /* Node specific styles based on depth */
        .node.root rect {
            fill: #10B981;
            stroke: #059669;
            stroke-width: 3px;
        }
        .node.root text {
            fill: #fff;
            font-weight: 700;
            font-size: 16px;
        }
        .node.collapsed rect {
            fill: #ecfdf5; /* emerald-50 */
            stroke-dasharray: 4;
        }

        .sidebar-item:hover {
            background-color: #ecfdf5;
            color: #047857;
        }
        .sidebar-item.active {
            background-color: #10B981;
            color: white;
        }
        /* CustomScrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1; 
        }
        ::-webkit-scrollbar-thumb {
            background: #d1d5db; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af; 
        }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden">

     <!-- Header -->
    <header class="bg-white border-b border-gray-200 h-16 flex items-center px-6 shadow-sm z-10 relative justify-between">
        <div class="flex items-center gap-3">
            <button id="sidebar-toggle" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 mr-2 focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <img src="/logo.png" alt="Logo" class="h-10 w-10">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Quran Mind Map</h1>
                <p class="text-xs text-gray-500">Visualisasi Peta Konsep Al-Qur'an</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button id="fit-screen-btn" class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors flex items-center gap-2 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
                <span class="font-medium">Fit to Screen</span>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <div class="flex flex-1 overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="w-72 bg-white border-r border-gray-200 flex flex-col z-10 shrink-0 transition-all duration-300" id="sidebar">
            <div class="p-4 border-b border-gray-100">
                <input type="text" id="search" placeholder="Cari Surat..." 
                       class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
            </div>
            <div class="flex-1 overflow-y-auto" id="surah-list">
                @foreach($surahs as $surah)
                <div class="sidebar-item cursor-pointer px-6 py-3 border-b border-gray-50 transition-colors"
                     onclick="loadSurah({{ $surah->id }})"
                     data-name="{{ strtolower($surah->name) }} {{ strtolower($surah->english_name) }}">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">
                            <span class="text-xs font-bold bg-gray-100 text-gray-600 rounded-full h-6 w-6 inline-flex items-center justify-center mr-2">{{ $surah->number }}</span>
                            {{ $surah->name }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $surah->english_name }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </aside>

        <!-- Visualization Area -->
        <main class="flex-1 relative bg-slate-50 overflow-hidden" id="viz-container">
            <div id="loading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-80 z-20 hidden">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-emerald-500"></div>
            </div>
            <div id="empty-state" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                <img src="/logo.png" class="h-24 w-24 opacity-20 mb-4 grayscale">
                <p>Pilih Surat dari menu di samping untuk melihat Mind Map</p>
            </div>
            <svg id="mindmap-svg" width="100%" height="100%">
                <defs>
                    <filter id="drop-shadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur in="SourceAlpha" stdDeviation="2"/>
                        <feOffset dx="1" dy="2" result="offsetblur"/>
                        <feComponentTransfer>
                            <feFuncA type="linear" slope="0.2"/>
                        </feComponentTransfer>
                        <feMerge>
                            <feMergeNode/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>
            </svg>
            
            <!-- Floating Zoom Controls -->
            <div class="absolute bottom-6 right-6 flex flex-col gap-2 z-10">
                <button id="zoom-in-btn" class="p-3 bg-white text-gray-700 rounded-lg hover:bg-emerald-50 hover:text-emerald-600 transition-colors shadow-lg border border-gray-200" title="Zoom In">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                    </svg>
                </button>
                <button id="zoom-out-btn" class="p-3 bg-white text-gray-700 rounded-lg hover:bg-emerald-50 hover:text-emerald-600 transition-colors shadow-lg border border-gray-200" title="Zoom Out">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" />
                    </svg>
                </button>
            </div>
        </main>
    </div>

    <script>
        // Search Functionality
        document.getElementById('search').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.sidebar-item').forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(term)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Toggle Sidebar
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle');
        const vizContainer = document.getElementById('viz-container');
        
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-ml-72');
            // Trigger window resize event or update D3 manually to recenter if needed
            setTimeout(() => {
               // Optional: recalibrate center if we want to keep the tree centered in the new view
               // But zoom interaction handles this naturally for the user.
            }, 300);
        });

        // D3 Visualization Reference
        let root;
        let svg = d3.select("#mindmap-svg");
        let g = svg.append("g").attr("transform", "translate(100,0)");
        
        // Zoom functionality
        let zoom = d3.zoom()
            .scaleExtent([0.1, 4])
            .on("zoom", (event) => {
                g.attr("transform", event.transform);
            });
        svg.call(zoom).on("dblclick.zoom", null);
        
        let i = 0;
        const duration = 750;

        function update(source) {
            
            // Horizontal Tree Layout with compact spacing matching the screenshot
            // Reduced vertical spacing to 80 for "neat" stacking
            const tree = d3.tree().nodeSize([80, 400]); 
            const treeData = tree(root);

            // Compute the new tree layout.
            const nodes = treeData.descendants();
            const links = treeData.links();

            // Normalize for fixed-depth with increased horizontal spacing
            nodes.forEach(d => { d.y = d.depth * 450; }); 

            // Removed force simulation to ensure perfectly aligned tree structure
            // relying on d3.tree() for neatness

            // ****************** Nodes section ***************************

            const node = g.selectAll('g.node')
                .data(nodes, d => d.id || (d.id = ++i));

            // Enter any new modes at the parent's previous position.
            const nodeEnter = node.enter().append('g')
                .attr('class', d => 'node ' + (d.depth === 0 ? 'root' : '') + (d._children ? ' collapsed' : ''))
                .attr("transform", d => `translate(${source.y0},${source.x0})`)
                .on('click', click)
                .call(d3.drag()
                    .on("start", dragstarted)
                    .on("drag", dragged)
                    .on("end", dragended));

            // Add Rect for the nodes (Card style)
            const rect = nodeEnter.append('rect')
                .attr('rx', 8)
                .attr('ry', 8)
                .attr('width', 0) 
                .attr('height', 0)
                .attr('y', 0);

            // Add labels for the nodes
            const textGroup = nodeEnter.append('text')
                .attr("x", 15)
                .attr("y", 0)
                .style("fill-opacity", 1e-6);


            textGroup.append('tspan')
                .attr("class", "node-label")
                .text(d => d.data.name)
                .attr("x", 15)
                .attr("dy", d => d.data.ayah_range ? "-0.2em" : "0.35em");

            textGroup.append('tspan')
                .attr("class", "ayah-range")
                .text(d => d.data.ayah_range || "")
                .attr("x", 15)
                .attr("dy", d => d.data.ayah_range ? "1.4em" : "0");

            // UPDATE
            const nodeUpdate = nodeEnter.merge(node);

            // Transition to the proper position for the node
            nodeUpdate.transition()
                .duration(duration)
                .attr("transform", d => `translate(${d.y},${d.x})`);

            // Update the node attributes and style
            nodeUpdate.select('rect')
                .attr('width', d => {
                    // Calculate width based on text with more generous padding
                    // Increased multiplier from 7 to 8 for better accuracy
                    const labelLen = d.data.name.length * 8; 
                    const rangeLen = (d.data.ayah_range || "").length * 7;
                    // Increased padding from 30 to 50 to prevent cutoff
                    return Math.max(180, Math.max(labelLen, rangeLen) + 50);
                })
                .attr('height', d => d.data.ayah_range ? 54 : 44) // Slight increase
                .attr('y', d => d.data.ayah_range ? -27 : -22)
                .attr('class', d => (d.depth === 0 ? 'root' : '') + (d._children ? ' collapsed' : ''));

            nodeUpdate.select('text')
                .style("fill-opacity", 1);


            // Remove any exiting nodes
            const nodeExit = node.exit().transition()
                .duration(duration)
                .attr("transform", d => `translate(${source.y},${source.x})`)
                .remove();

            nodeExit.select('rect')
                .attr('width', 0)
                .attr('height', 0);

            nodeExit.select('text')
                .style('fill-opacity', 1e-6);

            // ****************** Links section ***************************

            const link = g.selectAll('path.link')
                .data(links, d => d.target.id);

            // Enter any new links at the parent's previous position.
            const linkEnter = link.enter().insert('path', "g")
                .attr("class", "link")
                .attr('d', d => {
                     const o = {x: source.x0, y: source.y0, data: source.data};
                     return diagonal(o, o);
                });

            // UPDATE
            const linkUpdate = linkEnter.merge(link);

            // Transition back to the parent element position
            linkUpdate.transition()
                .duration(duration)
                .attr('d', d => diagonal(d.source, d.target));

            // Remove any exiting links
            const linkExit = link.exit().transition()
                .duration(duration)
                .attr('d', d => {
                    const o = {x: source.x, y: source.y, data: source.data};
                    return diagonal(o, o);
                })
                .remove();

            // Store the old positions for transition.
            nodes.forEach(d => {
                d.x0 = d.x;
                d.y0 = d.y;
            });

            function diagonal(s, d) {
                // Horizontal Bezier
                // Connect to right edge of source and left edge of target
                // Source Width approximation
                let sWidth = 0;
                if (s.data) {
                    const sLabelLen = (s.data.name || "").length * 7;
                    const sRangeLen = (s.data.ayah_range || "").length * 6;
                    sWidth = Math.max(140, Math.max(sLabelLen, sRangeLen) + 30);
                }
                
                const path = `M ${s.y + sWidth} ${s.x}
                        C ${(s.y + sWidth + d.y) / 2} ${s.x},
                          ${(s.y + sWidth + d.y) / 2} ${d.x},
                          ${d.y} ${d.x}`;

                return path;
            }

            // Toggle children on click.
            function click(event, d) {
                if (d.children) {
                    d._children = d.children;
                    d.children = null;
                } else {
                    d.children = d._children;
                    d._children = null;
                }
                update(d);
            }

            // Drag functions
            function dragstarted(event, d) {
                event.sourceEvent.stopPropagation();
                d3.select(this).raise().classed("active", true);
            }

            function dragged(event, d) {
                // Horizontal tree: x is vertical, y is horizontal in visual space
                // d.x and d.y are data coordinates.
                // transform is translate(d.y, d.x)
                
                // Update data coordinates based on mouse movement
                // We need to invert the mapping: 
                // Visual X comes from d.y
                // Visual Y comes from d.x
                
                d.y += event.dx;
                d.x += event.dy;
                
                d3.select(this).attr("transform", `translate(${d.y},${d.x})`);
                
                // Update links on the fly
                svg.selectAll('path.link').filter(l => l.source === d || l.target === d).attr('d', l => diagonal(l.source, l.target));
            }

            function dragended(event, d) {
                d3.select(this).classed("active", false);
            }
        }
        
        async function loadSurah(id) {
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('empty-state').classList.add('hidden');
            
            // Highlight sidebar
            document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            // If on mobile (small screen), close sidebar after selection
            if(window.innerWidth < 768) {
                sidebar.classList.add('-ml-72');
            }

            try {
                const response = await fetch(`/api/surah/${id}`);
                const data = await response.json();

                root = d3.hierarchy(data, d => d.children);
                // Set initial position to vertical center of viewport
                root.x0 = document.getElementById('viz-container').clientHeight / 2;
                root.y0 = 0;

                // Expand all nodes initially as per user request to "expose all if many"
                // Or maybe just increase spacing.
                // Let's keep the standard behavior but maybe expand 2 levels deep?
                // User said: "jika banyak bisa terekspose semua misal di bagi rata gitu"
                // He might mean "Expand All" or just "Space them out so they are all visible".
                // I increased nodeSize above (80px).
                // Collapse all children initially for cleaner view
                if (root.children) {
                    root.children.forEach(collapse);
                }

                update(root);
                
                // Auto-center the tree on initial load
                setTimeout(() => {
                    centerTree();
                }, 100);

            } catch (error) {
                console.error("Error loading data", error);
                alert("Gagal memuat data surat.");
            } finally {
                document.getElementById('loading').classList.add('hidden');
            }
        }

        function collapse(d) {
            if(d.children) {
                d._children = d.children
                d._children.forEach(collapse)
                d.children = null
            }
        }

        // Fit to Screen button handler
        document.getElementById('fit-screen-btn').addEventListener('click', () => {
            centerNode(root);
        });

        // Zoom In button handler
        document.getElementById('zoom-in-btn').addEventListener('click', () => {
            svg.transition().duration(300).call(zoom.scaleBy, 1.3);
        });

        // Zoom Out button handler
        document.getElementById('zoom-out-btn').addEventListener('click', () => {
            svg.transition().duration(300).call(zoom.scaleBy, 0.7);
        });

        function centerNode(source) {
            const container = document.getElementById('viz-container');
            const width = container.clientWidth;
            const height = container.clientHeight;
            
            // Calculate bounds of the tree
            let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
            root.each(d => {
                if (d.x < minX) minX = d.x;
                if (d.x > maxX) maxX = d.x;
                if (d.y < minY) minY = d.y;
                if (d.y > maxY) maxY = d.y;
            });
            
            const treeHeight = maxX - minX + 100; // Add padding
            const treeWidth = maxY - minY + 300; // Add padding

            // Calculate scale to fit
            let scale = 1;
             // If tree is larger than container, shrink it
            if (treeHeight > height - 40) {
                 scale = (height - 40) / treeHeight;
            }
            // Clamp scale
            scale = Math.min(1, Math.max(0.2, scale));

            // Center vertically
            const initialY = (height / 2) - ((minX + maxX) / 2) * scale;
            const initialX = 50; // Padding left

             const t = d3.zoomIdentity.translate(initialX, initialY).scale(scale);
            svg.transition().duration(750).call(zoom.transform, t);
        }

    </script>
</body>
</html>
