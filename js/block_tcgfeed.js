M.block_tcgfeed =
    {
        init: function()
        {
            this.lockdown(document.getElementById('tcgblockwrapper'));
            console.log('block_tcgfeed js loaded and locked');
            return true;
        },

        // Strip out any embedded styles in the feed
        lockdown: function(node)
        {
            // Node type 1 is an element.
            if(node.nodeType===1)
            {
                node.removeAttribute('style');
            }
            node=node.firstChild;
            while(node)
            {
                this.lockdown(node);
                node=node.nextSibling;
            }
        },

        close_collapsible: function(me,event)
        {
            if(!event.ctrlKey)
            {
                var temp=me.getAttributeNode('state').value;
                var elements=document.getElementsByClassName('tcgfeed_job');
                console.log(elements);
                for(i=0;i<elements.length;i++)
                {
                    elements[i].getAttributeNode('state').value=0;
                }
                me.getAttributeNode('state').value=temp;
            }
        },

        updateitem: function(obj,contents)
        {
            if(typeof contents !== 'undefined'  && contents !== 'failed')
            {
                obj.innerHTML=contents;
            }
        },

        // params is an array
        setitem: function(obj,func,params)
        {
            var r=new XMLHttpRequest;
            r.onload = function (e) {
                if (r.readyState === 4)
                {
                    if (r.status === 200)
                    {
                        M.block_tcgfeed.updateitem(obj,r.responseText);
                    }
                }
            }

            console.log('blocks/tcgfeed/brain.php?fn='+func+'&'+encodeURI(params));
            r.open('GET','/blocks/tcgfeed/brain.php?fn='+func+'&'+encodeURI(params));
            r.send(null);
        },

        setsector: function(obj,user)
        {
            this.setitem(document.getElementById('tcgblockwrapper'),'update_sector',
                         ['user='+user,'sector='+obj.value].join('&'));
        },

        settype: function(obj,user)
        {
            this.setitem(document.getElementById('tcgblockwrapper'),'update_type',
                         ['user='+user,'type='+obj.value].join('&'));
        },

        settime: function(obj,user)
        {
            this.setitem(document.getElementById('tcgblockwrapper'),'update_time',
                         ['user='+user,'time='+obj.value].join('&'));
        },

        test: function(object,text)
        {
            object.innerHTML='AAAA'+text;
        },
    };
